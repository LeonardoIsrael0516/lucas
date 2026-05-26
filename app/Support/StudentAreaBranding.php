<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Plugins\WhiteLabel\ApplyWhiteLabelConfig;
use Plugins\WhiteLabel\WhiteLabelSetting;

/**
 * Branding da área do aluno (/area-membros sidebar): cor + variantes de logo.
 * Logos vêm do White Label (mesmos assets do painel).
 */
final class StudentAreaBranding
{
    /**
     * @return array{
     *     primary: string,
     *     logo_url: ?string,
     *     logo_light_url: ?string,
     *     logo_light_collapsed_url: ?string,
     *     logo_dark_url: ?string,
     *     logo_dark_collapsed_url: ?string,
     *     favicon_url: ?string
     * }
     */
    public static function forTenant(?int $tenantId): array
    {
        $primaryFromSetting = $tenantId ? (string) Setting::get('student_area_primary', '', $tenantId) : '';
        $primary = trim($primaryFromSetting) !== '' ? trim($primaryFromSetting) : self::whiteLabelPrimary($tenantId);

        [$appLogo, $appLogoDark, $appLogoIcon, $appLogoIconDark] = self::whiteLabelLogoStrings($tenantId);
        $favicon = self::nonEmptyUrl((string) config('getfy.favicon_url', ''))
            ?? $appLogoIconDark
            ?? $appLogoIcon;

        return [
            'primary' => $primary,
            'logo_url' => $appLogoDark !== null ? $appLogoDark : $appLogo,
            'logo_light_url' => $appLogo,
            'logo_light_collapsed_url' => $appLogoIcon,
            'logo_dark_url' => $appLogoDark,
            'logo_dark_collapsed_url' => $appLogoIconDark,
            'favicon_url' => $favicon,
        ];
    }

    /**
     * Favicon do hub: prioriza o configurado em um curso que o aluno possui.
     *
     * @param  iterable<\App\Models\Product>  $ownedProducts
     */
    public static function mergeHubFaviconFromOwnedProducts(array $branding, iterable $ownedProducts): array
    {
        foreach ($ownedProducts as $product) {
            $config = is_array($product->member_area_config) ? $product->member_area_config : [];
            $url = $config['logos']['favicon'] ?? $config['pwa']['favicon'] ?? null;
            if (is_string($url) && trim($url) !== '') {
                $branding['favicon_url'] = trim($url);
                break;
            }
        }

        return $branding;
    }

    /**
     * Branding do hub com favicon dos cursos do aluno quando existir.
     */
    public static function forUser(?User $user): array
    {
        $tenantId = $user !== null ? StudentAreaTenant::idForUser($user) : null;

        $branding = self::forTenant($tenantId);
        if ($user === null) {
            return $branding;
        }

        $owned = $user->products()
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $p) => $p->type === Product::TYPE_AREA_MEMBROS);

        return self::mergeHubFaviconFromOwnedProducts($branding, $owned);
    }

    private static function whiteLabelPrimary(?int $tenantId): string
    {
        if (! class_exists(WhiteLabelSetting::class)) {
            return (string) config('getfy.theme_primary', '#0ea5e9');
        }

        try {
            if (! Schema::hasTable('white_label_settings')) {
                return (string) config('getfy.theme_primary', '#0ea5e9');
            }
        } catch (\Throwable) {
            return (string) config('getfy.theme_primary', '#0ea5e9');
        }

        $global = WhiteLabelSetting::query()->whereNull('tenant_id')->first();
        $globalData = is_array($global?->data) ? $global->data : [];
        $tenantData = [];
        if ($tenantId !== null && $tenantId > 0) {
            $tenant = WhiteLabelSetting::query()->where('tenant_id', $tenantId)->first();
            $tenantData = is_array($tenant?->data) ? $tenant->data : [];
        }
        $merged = ApplyWhiteLabelConfig::mergeLayers($globalData, $tenantData);
        $primary = $merged['theme_primary'] ?? null;
        if (is_string($primary) && trim($primary) !== '') {
            return trim($primary);
        }

        return (string) config('getfy.theme_primary', '#0ea5e9');
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private static function whiteLabelLogoStrings(?int $tenantId): array
    {
        $fromConfig = fn (string $key): ?string => self::nonEmptyUrl((string) config("getfy.{$key}", ''));

        if (! class_exists(WhiteLabelSetting::class)) {
            return [
                $fromConfig('app_logo'),
                $fromConfig('app_logo_dark'),
                $fromConfig('app_logo_icon'),
                $fromConfig('app_logo_icon_dark'),
            ];
        }

        try {
            if (! Schema::hasTable('white_label_settings')) {
                return [
                    $fromConfig('app_logo'),
                    $fromConfig('app_logo_dark'),
                    $fromConfig('app_logo_icon'),
                    $fromConfig('app_logo_icon_dark'),
                ];
            }
        } catch (\Throwable) {
            return [
                $fromConfig('app_logo'),
                $fromConfig('app_logo_dark'),
                $fromConfig('app_logo_icon'),
                $fromConfig('app_logo_icon_dark'),
            ];
        }

        $global = WhiteLabelSetting::query()->whereNull('tenant_id')->first();
        $globalData = is_array($global?->data) ? $global->data : [];
        $tenantData = [];
        if ($tenantId !== null && $tenantId > 0) {
            $tenant = WhiteLabelSetting::query()->where('tenant_id', $tenantId)->first();
            $tenantData = is_array($tenant?->data) ? $tenant->data : [];
        }
        $merged = ApplyWhiteLabelConfig::mergeLayers($globalData, $tenantData);

        $pick = function (string $jsonKey, string $configKey) use ($merged, $fromConfig): ?string {
            $v = $merged[$jsonKey] ?? null;

            return self::nonEmptyUrl(is_string($v) ? $v : '') ?? $fromConfig($configKey);
        };

        return [
            $pick('app_logo', 'app_logo'),
            $pick('app_logo_dark', 'app_logo_dark'),
            $pick('app_logo_icon', 'app_logo_icon'),
            $pick('app_logo_icon_dark', 'app_logo_icon_dark'),
        ];
    }

    private static function nonEmptyUrl(string $value): ?string
    {
        $t = trim($value);

        return $t === '' ? null : $t;
    }
}
