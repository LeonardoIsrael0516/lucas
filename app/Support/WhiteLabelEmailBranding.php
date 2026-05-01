<?php

namespace App\Support;

use App\Plugins\PluginRegistry;

final class WhiteLabelEmailBranding
{
    /**
     * @return array{0: string, 1: string|null} [appName, logoUrl]
     */
    public static function resolve(?int $tenantId): array
    {
        $defaultName = (string) config('app.name', 'Getfy');
        $defaultLogo = 'https://cdn.getfy.cloud/logo-white.png';

        try {
            $enabled = collect(PluginRegistry::enabled())->contains(fn ($p) => ($p['slug'] ?? null) === 'white-label');
            if (! $enabled) {
                return [$defaultName, $defaultLogo];
            }
        } catch (\Throwable) {
            return [$defaultName, $defaultLogo];
        }

        if (! class_exists(\Plugins\WhiteLabel\WhiteLabelSetting::class) || ! class_exists(\Plugins\WhiteLabel\ApplyWhiteLabelConfig::class)) {
            return [$defaultName, $defaultLogo];
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('white_label_settings')) {
                return [$defaultName, $defaultLogo];
            }
        } catch (\Throwable) {
            return [$defaultName, $defaultLogo];
        }

        try {
            $global = \Plugins\WhiteLabel\WhiteLabelSetting::query()->whereNull('tenant_id')->first();
            $tenant = $tenantId !== null
                ? \Plugins\WhiteLabel\WhiteLabelSetting::query()->where('tenant_id', $tenantId)->first()
                : null;

            $globalData = is_array($global?->data) ? $global->data : [];
            $tenantData = is_array($tenant?->data) ? $tenant->data : [];
            $branding = \Plugins\WhiteLabel\ApplyWhiteLabelConfig::mergeLayers($globalData, $tenantData);

            $appName = trim((string) ($branding['app_name'] ?? ''));
            if ($appName === '') {
                $appName = $defaultName;
            }

            $logoUrl = null;
            $logoRaw = trim((string) ($branding['app_logo'] ?? ''));
            if ($logoRaw !== '' && filter_var($logoRaw, FILTER_VALIDATE_URL)) {
                $logoUrl = $logoRaw;
            } else {
                $logoUrl = $defaultLogo;
            }

            return [$appName, $logoUrl];
        } catch (\Throwable) {
            return [$defaultName, $defaultLogo];
        }
    }
}
