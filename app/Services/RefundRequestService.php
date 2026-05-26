<?php

namespace App\Services;

use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Support\RefundSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundRequestService
{
    public function __construct(
        private ProductAccessService $productAccessService,
    ) {}

    /**
     * @return array{can_request: bool, pending: bool, status: string|null, days_remaining: int|null, order_id: int|null}
     */
    public function refundMetaForCourse(Product $product, User $user): array
    {
        $tenantId = (int) $product->tenant_id;
        $empty = [
            'can_request' => false,
            'pending' => false,
            'status' => null,
            'days_remaining' => null,
            'order_id' => null,
        ];

        if (! RefundSettings::enabled($tenantId)) {
            return $empty;
        }

        if ($product->type !== Product::TYPE_AREA_MEMBROS) {
            return $empty;
        }

        if (! $this->productAccessService->hasActiveAccess($product, $user)) {
            return $empty;
        }

        $order = $this->eligibleOrder($product, $user);
        if (! $order) {
            return $empty;
        }

        $existing = RefundRequest::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if ($existing && in_array($existing->status, [RefundRequest::STATUS_PENDING, RefundRequest::STATUS_APPROVED], true)) {
            return [
                'can_request' => false,
                'pending' => $existing->status === RefundRequest::STATUS_PENDING,
                'status' => $existing->status,
                'days_remaining' => $this->daysRemaining($order, $tenantId),
                'order_id' => null,
            ];
        }

        if ($existing && $existing->status === RefundRequest::STATUS_DENIED) {
            return [
                'can_request' => true,
                'pending' => false,
                'status' => RefundRequest::STATUS_DENIED,
                'days_remaining' => $this->daysRemaining($order, $tenantId),
                'order_id' => $order->id,
            ];
        }

        return [
            'can_request' => true,
            'pending' => false,
            'status' => null,
            'days_remaining' => $this->daysRemaining($order, $tenantId),
            'order_id' => $order->id,
        ];
    }

    public function create(User $user, Product $product, string $reason): RefundRequest
    {
        $tenantId = (int) $product->tenant_id;

        if (! RefundSettings::enabled($tenantId)) {
            throw ValidationException::withMessages([
                'reason' => 'O reembolso não está disponível no momento.',
            ]);
        }

        if ($product->type !== Product::TYPE_AREA_MEMBROS) {
            throw ValidationException::withMessages([
                'product_id' => 'Este produto não permite solicitação de reembolso.',
            ]);
        }

        if (! $this->productAccessService->hasActiveAccess($product, $user)) {
            throw ValidationException::withMessages([
                'product_id' => 'Você não tem acesso ativo a este curso.',
            ]);
        }

        $order = $this->eligibleOrder($product, $user);
        if (! $order) {
            throw ValidationException::withMessages([
                'product_id' => 'Não há pedido elegível para reembolso neste curso.',
            ]);
        }

        $blocking = RefundRequest::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [RefundRequest::STATUS_PENDING, RefundRequest::STATUS_APPROVED])
            ->exists();

        if ($blocking) {
            throw ValidationException::withMessages([
                'product_id' => 'Já existe uma solicitação de reembolso para este pedido.',
            ]);
        }

        return RefundRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'reason' => $reason,
            'status' => RefundRequest::STATUS_PENDING,
        ]);
    }

    public function approve(RefundRequest $request, User $reviewer): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Esta solicitação já foi analisada.',
            ]);
        }

        DB::transaction(function () use ($request, $reviewer) {
            $request->load(['user', 'product', 'order']);
            $user = $request->user;
            $product = $request->product;
            $order = $request->order;

            if (! $user || ! $product || ! $order) {
                throw ValidationException::withMessages([
                    'status' => 'Dados da solicitação incompletos.',
                ]);
            }

            $request->update([
                'status' => RefundRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->productAccessService->revoke($user, $product);

            Subscription::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_CANCELLED]);

            if ($order->status !== 'refunded') {
                $order->update(['status' => 'refunded']);
                event(new OrderRefunded($order->fresh()));
            }
        });
    }

    public function deny(RefundRequest $request, User $reviewer, ?string $adminNote = null): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Esta solicitação já foi analisada.',
            ]);
        }

        $request->update([
            'status' => RefundRequest::STATUS_DENIED,
            'admin_note' => $adminNote,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    private function eligibleOrder(Product $product, User $user): ?Order
    {
        $tenantId = (int) $product->tenant_id;
        $days = RefundSettings::days($tenantId);
        $deadline = now()->subDays($days);

        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', $deadline)
            ->where('amount', '>', 0)
            ->orderByDesc('created_at')
            ->first();

        if (! $order) {
            return null;
        }

        $hasBlocking = RefundRequest::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [RefundRequest::STATUS_PENDING, RefundRequest::STATUS_APPROVED])
            ->exists();

        return $hasBlocking ? null : $order;
    }

    private function daysRemaining(Order $order, int $tenantId): ?int
    {
        $days = RefundSettings::days($tenantId);
        $expiresAt = Carbon::parse($order->created_at)->addDays($days)->endOfDay();
        $remaining = (int) now()->diffInDays($expiresAt, false);

        return max(0, $remaining);
    }
}
