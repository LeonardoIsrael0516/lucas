<?php

namespace App\Http\Controllers;

use App\Models\MemberPushSubscription;
use App\Models\Product;
use App\Services\MemberPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentPushNotificationsController extends Controller
{
    public function __construct(
        private MemberPushService $memberPushService,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $products = $this->pushEnabledProducts($tenantId);

        return Inertia::render('PushNotifications/Index', [
            'pageTitle' => 'Notificações push',
            'products' => $products,
            'total_subscribers' => collect($products)->sum('subscribers_count'),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $tenantId = (int) auth()->user()->tenant_id;
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:200'],
            'product_id' => ['nullable', 'string'],
            'all_products' => ['sometimes', 'boolean'],
        ]);

        $allProducts = (bool) ($validated['all_products'] ?? false);
        $productId = trim((string) ($validated['product_id'] ?? ''));

        if (! $allProducts && $productId === '') {
            return response()->json(['message' => 'Selecione um curso ou marque envio para todos.'], 422);
        }

        if (! $allProducts) {
            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('type', Product::TYPE_AREA_MEMBROS)
                ->where('id', $productId)
                ->first();
            if (! $product) {
                return response()->json(['message' => 'Curso não encontrado.'], 404);
            }
            $result = $this->memberPushService->sendToProduct($product, $validated['title'], $validated['body']);

            return response()->json([
                'success' => $result['sent'] > 0,
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'expired' => $result['expired'],
                'message' => $result['message'],
                'products' => [[
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sent' => $result['sent'],
                    'message' => $result['message'],
                ]],
            ]);
        }

        $batch = $this->memberPushService->sendToTenantProducts($tenantId, $validated['title'], $validated['body'], null, true);
        $message = $batch['sent'] > 0
            ? "Notificação enviada para {$batch['sent']} destinatário(s) no total."
            : 'Nenhum destinatário inscrito ou nenhum curso com push ativo.';
        if ($batch['expired'] > 0) {
            $message .= " {$batch['expired']} inscrição(ões) expirada(s) removida(s).";
        }

        return response()->json([
            'success' => $batch['sent'] > 0,
            'sent' => $batch['sent'],
            'failed' => $batch['failed'],
            'expired' => $batch['expired'],
            'message' => $message,
            'products' => $batch['products'],
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string, push_enabled: bool, has_vapid: bool, subscribers_count: int, member_builder_url: string}>
     */
    private function pushEnabledProducts(int $tenantId): array
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $pwa = $product->member_area_config['pwa'] ?? [];
                $pushEnabled = (bool) ($pwa['push_enabled'] ?? false);
                $hasVapid = trim((string) ($pwa['vapid_public'] ?? '')) !== ''
                    && trim((string) ($pwa['vapid_private'] ?? '')) !== '';

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'push_enabled' => $pushEnabled,
                    'has_vapid' => $hasVapid,
                    'subscribers_count' => MemberPushSubscription::where('product_id', $product->id)->count(),
                    'member_builder_url' => route('member-builder.index', $product),
                    'ready' => $pushEnabled && $hasVapid,
                ];
            })
            ->values()
            ->all();
    }
}
