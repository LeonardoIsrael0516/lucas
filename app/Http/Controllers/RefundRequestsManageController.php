<?php

namespace App\Http\Controllers;

use App\Models\RefundRequest;
use App\Services\RefundRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RefundRequestsManageController extends Controller
{
    public function __construct(
        private RefundRequestService $refundRequestService,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $status = (string) $request->query('status', 'pending');

        $query = RefundRequest::query()
            ->where('tenant_id', $tenantId)
            ->with(['user:id,name,email', 'product:id,name', 'order:id,amount,status,created_at'])
            ->orderByDesc('created_at');

        if ($status === 'pending') {
            $query->where('status', RefundRequest::STATUS_PENDING);
        } elseif ($status === 'approved') {
            $query->where('status', RefundRequest::STATUS_APPROVED);
        } elseif ($status === 'denied') {
            $query->where('status', RefundRequest::STATUS_DENIED);
        }

        $requests = $query->paginate(25)->through(fn (RefundRequest $r) => $this->serializeListItem($r));

        return Inertia::render('RefundRequests/Index', [
            'pageTitle' => 'Reembolsos',
            'requests' => $requests,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(RefundRequest $refundRequest): Response
    {
        $this->authorizeRequest($refundRequest);
        $refundRequest->load(['user:id,name,email', 'product:id,name', 'order', 'reviewedBy:id,name']);

        return Inertia::render('RefundRequests/Show', [
            'pageTitle' => 'Reembolso #'.$refundRequest->id,
            'refund_request' => $this->serializeDetail($refundRequest),
        ]);
    }

    public function approve(RefundRequest $refundRequest): RedirectResponse
    {
        $this->authorizeRequest($refundRequest);

        try {
            $this->refundRequestService->approve($refundRequest, auth()->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('refund-requests.index', ['status' => 'approved'])
            ->with('success', 'Reembolso aprovado. O acesso ao curso foi revogado e o pedido marcado como reembolsado.');
    }

    public function deny(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $this->authorizeRequest($refundRequest);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->refundRequestService->deny(
                $refundRequest,
                auth()->user(),
                isset($validated['admin_note']) ? trim($validated['admin_note']) : null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('refund-requests.index', ['status' => 'denied'])
            ->with('success', 'Solicitação de reembolso negada.');
    }

    private function authorizeRequest(RefundRequest $refundRequest): void
    {
        if ($refundRequest->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(RefundRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'student_name' => $r->user?->name ?? '—',
            'student_email' => $r->user?->email ?? '',
            'product_name' => $r->product?->name ?? '—',
            'order_amount' => $r->order ? (float) $r->order->amount : null,
            'order_created_at' => $r->order?->created_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(RefundRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'reason' => $r->reason,
            'admin_note' => $r->admin_note,
            'created_at' => $r->created_at?->toIso8601String(),
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'reviewed_by_name' => $r->reviewedBy?->name,
            'student' => [
                'name' => $r->user?->name ?? '—',
                'email' => $r->user?->email ?? '',
            ],
            'product' => [
                'id' => $r->product_id,
                'name' => $r->product?->name ?? '—',
            ],
            'order' => $r->order ? [
                'id' => $r->order->id,
                'amount' => (float) $r->order->amount,
                'status' => $r->order->status,
                'created_at' => $r->order->created_at?->toIso8601String(),
                'vendas_url' => '/vendas?search='.$r->order->id,
            ] : null,
        ];
    }
}
