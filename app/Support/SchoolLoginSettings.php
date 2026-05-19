<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberAreaResolver;
use App\Services\StorageService;
use Illuminate\Http\Request;

/**
 * Configuração global da tela de login da escola (tenant), armazenada em {@see Setting}.
 */
final class SchoolLoginSettings
{
    public static function resolveTenantId(?Request $request = null): ?int
    {
        if ($request !== null) {
            $resolved = app(MemberAreaResolver::class)->resolve($request);
            if ($resolved && isset($resolved['product']) && $resolved['product'] instanceof Product) {
                return (int) $resolved['product']->tenant_id;
            }
        }

        $adminId = User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');

        return $adminId !== null ? (int) $adminId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function brandingForTenant(?int $tenantId): array
    {
        if ($tenantId === null) {
            return self::emptyBranding();
        }

        $logoPath = (string) Setting::get('login_logo', '', $tenantId);
        $bgPath = (string) Setting::get('login_background_image', '', $tenantId);
        $logoUrl = self::storageUrl($tenantId, $logoPath);
        $backgroundImageUrl = self::storageUrl($tenantId, $bgPath);

        $withoutPassword = Setting::get('login_without_password', '0', $tenantId);
        $loginWithoutPassword = $withoutPassword === '1' || $withoutPassword === 1 || $withoutPassword === true;

        return [
            'title' => (string) Setting::get('login_title', 'Área de Membros', $tenantId),
            'subtitle' => (string) Setting::get('login_subtitle', 'Entre com seu e-mail e senha', $tenantId),
            'background_color' => (string) Setting::get('login_background_color', '#18181b', $tenantId),
            'primary_color' => (string) Setting::get('login_primary_color', '#0ea5e9', $tenantId),
            'logo_url' => $logoUrl,
            'background_image_url' => $backgroundImageUrl,
            'login_without_password' => $loginWithoutPassword,
            'password_mode' => (string) Setting::get('login_password_mode', 'auto', $tenantId),
        ];
    }

    private static function storageUrl(int $tenantId, string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        try {
            $storage = new StorageService($tenantId);

            return $storage->exists($path) ? $storage->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyBranding(): array
    {
        return [
            'title' => 'Área de Membros',
            'subtitle' => 'Entre com seu e-mail e senha',
            'background_color' => '#18181b',
            'primary_color' => '#0ea5e9',
            'logo_url' => null,
            'background_image_url' => null,
            'login_without_password' => false,
            'password_mode' => 'auto',
        ];
    }
}
