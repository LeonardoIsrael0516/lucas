<?php

namespace App\Services;

use App\Models\MemberNotification;
use App\Models\MemberPushSubscription;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class MemberPushService
{
    /**
     * @return array{sent: int, failed: int, expired: int, invalid: int, message: string}
     */
    public function sendToProduct(Product $product, string $title, string $body): array
    {
        if ($product->type !== Product::TYPE_AREA_MEMBROS) {
            return $this->result(0, 0, 0, 0, 'Produto inválido para push de área de membros.');
        }

        $config = is_array($product->member_area_config) ? $product->member_area_config : [];
        $pwa = $config['pwa'] ?? [];
        if (! ((bool) ($pwa['push_enabled'] ?? false))) {
            return $this->result(0, 0, 0, 0, 'Notificações push não estão habilitadas para esta área.');
        }

        $vapidPublic = trim((string) ($pwa['vapid_public'] ?? ''));
        $vapidPrivate = trim((string) ($pwa['vapid_private'] ?? ''));
        if ($vapidPublic === '' || $vapidPrivate === '') {
            return $this->result(0, 0, 0, 0, 'Chaves VAPID ausentes. Ative push no Member Builder e salve a configuração.');
        }

        $subscriptions = MemberPushSubscription::where('product_id', $product->id)->get();
        if ($subscriptions->isEmpty()) {
            return $this->result(0, 0, 0, 0, 'Nenhum destinatário inscrito neste curso.');
        }

        $subject = 'mailto:'.(config('mail.from.address') ?: 'noreply@'.parse_url((string) config('app.url'), PHP_URL_HOST));
        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ];
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
        ]);

        $sent = 0;
        $failed = 0;
        $expired = 0;
        $invalid = 0;
        $userIdsSent = [];

        try {
            $webPush = new WebPush($auth);
            foreach ($subscriptions as $sub) {
                $keys = $sub->keys ?? [];
                $authKey = trim((string) ($keys['auth'] ?? ''));
                $p256dh = trim((string) ($keys['p256dh'] ?? ''));
                if (! $sub->endpoint || $authKey === '' || $p256dh === '') {
                    $invalid++;

                    continue;
                }
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'auth' => $this->normalizeBase64KeyForPush($authKey),
                        'p256dh' => $this->normalizeBase64KeyForPush($p256dh),
                    ],
                ]);
                try {
                    $report = $webPush->sendOneNotification($subscription, $payload);
                    if ($report->isSuccess()) {
                        $sent++;
                        if ($sub->user_id) {
                            $userIdsSent[$sub->user_id] = true;
                        }
                    } elseif ($report->isSubscriptionExpired()) {
                        $expired++;
                        $sub->delete();
                    } else {
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('MemberPushService: falha ao enviar para subscription', [
                        'subscription_id' => $sub->id,
                        'product_id' => $product->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            foreach (array_keys($userIdsSent) as $userId) {
                MemberNotification::create([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'type' => 'push',
                    'title' => $title,
                    'body' => $body,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('MemberPushService: erro ao enviar push', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);

            return $this->result(0, 0, 0, 0, 'Erro ao enviar: '.$e->getMessage());
        }

        $message = $sent === 0
            ? 'Nenhum destinatário inscrito ou falha no envio.'
            : "Notificação enviada para {$sent} destinatário(s).";
        if ($expired > 0) {
            $message .= " {$expired} inscrição(ões) expirada(s) removida(s).";
        }

        return $this->result($sent, $failed, $expired, $invalid, $message);
    }

    /**
     * @return array{sent: int, failed: int, expired: int, products: array<int, array{product_id: string, name: string, sent: int}>}
     */
    public function sendToTenantProducts(int $tenantId, string $title, string $body, ?string $productId = null, bool $allProducts = false): array
    {
        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS);

        if (! $allProducts && $productId !== null && $productId !== '') {
            $query->where('id', $productId);
        }

        $products = $query->get()->filter(function (Product $product) {
            $pwa = $product->member_area_config['pwa'] ?? [];

            return (bool) ($pwa['push_enabled'] ?? false)
                && ! empty($pwa['vapid_public'])
                && ! empty($pwa['vapid_private']);
        });

        $totalSent = 0;
        $totalFailed = 0;
        $totalExpired = 0;
        $perProduct = [];

        foreach ($products as $product) {
            $result = $this->sendToProduct($product, $title, $body);
            $totalSent += $result['sent'];
            $totalFailed += $result['failed'];
            $totalExpired += $result['expired'];
            $perProduct[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sent' => $result['sent'],
                'message' => $result['message'],
            ];
        }

        return [
            'sent' => $totalSent,
            'failed' => $totalFailed,
            'expired' => $totalExpired,
            'products' => $perProduct,
        ];
    }

    /**
     * @return array{sent: int, failed: int, expired: int, invalid: int, message: string}
     */
    private function result(int $sent, int $failed, int $expired, int $invalid, string $message): array
    {
        return [
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
            'invalid' => $invalid,
            'message' => $message,
        ];
    }

    private function normalizeBase64KeyForPush(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return $key;
        }
        if (str_contains($key, '+') || str_contains($key, '/')) {
            return strtr($key, ['+' => '-', '/' => '_']);
        }

        return $key;
    }
}
