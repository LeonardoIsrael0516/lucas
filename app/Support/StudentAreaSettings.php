<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;

/**
 * Configuração global da área do aluno (tenant), com fallback legado em member_area_config do produto.
 */
final class StudentAreaSettings
{
    public static function tenantIdForUser(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        return StudentAreaTenant::idForUser($user);
    }

    public static function communityEnabled(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }
        $v = Setting::get('student_community_enabled', null, $tenantId);
        if ($v !== null) {
            return $v === '1' || $v === 1 || $v === true;
        }

        return self::legacyCommunityEnabledFromProducts($tenantId);
    }

    public static function communityUsersCanDeleteOwnPosts(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return true;
        }
        $v = Setting::get('student_community_users_can_delete_own_posts', null, $tenantId);
        if ($v !== null) {
            return $v === '1' || $v === 1 || $v === true;
        }

        return true;
    }

    /**
     * @return array{enabled: bool, completion_percent: int, title: string, signature_text: string, ...}
     */
    public static function certificateConfig(?int $tenantId): array
    {
        $defaults = self::defaultCertificateConfig();
        if ($tenantId === null) {
            return $defaults;
        }

        $stored = self::decodeJsonSetting('student_certificate', $tenantId);
        if ($stored !== []) {
            return array_replace_recursive($defaults, $stored);
        }

        return array_replace_recursive($defaults, self::legacyCertificateFromProducts($tenantId));
    }

    public static function certificateEnabled(?int $tenantId): bool
    {
        return (bool) (self::certificateConfig($tenantId)['enabled'] ?? false);
    }

    /**
     * @return array{enabled: bool, achievements: array<int, array<string, mixed>>}
     */
    public static function gamificationConfig(?int $tenantId): array
    {
        $defaults = ['enabled' => false, 'achievements' => []];
        if ($tenantId === null) {
            return $defaults;
        }

        $stored = self::decodeJsonSetting('student_gamification', $tenantId);
        if ($stored !== []) {
            return array_replace_recursive($defaults, $stored);
        }

        return array_replace_recursive($defaults, self::legacyGamificationFromProducts($tenantId));
    }

    public static function gamificationEnabled(?int $tenantId): bool
    {
        return (bool) (self::gamificationConfig($tenantId)['enabled'] ?? false);
    }

    /**
     * Percentual mínimo para certificado de um curso (por produto, fallback 100).
     */
    public static function certificateCompletionPercentForProduct(Product $product): int
    {
        $config = $product->member_area_config ?? [];
        $cert = $config['certificate'] ?? [];
        $percent = (int) ($cert['completion_percent'] ?? 0);

        return $percent > 0 ? min(100, $percent) : 100;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultCertificateConfig(): array
    {
        return [
            'enabled' => false,
            'title' => '',
            'completion_percent' => 100,
            'signature_text' => '',
            'font_family' => 'sans-serif',
            'duration_text' => '',
            'platform_name' => '',
            'primary_color' => '',
            'background_image_url' => '',
            'background_overlay_enabled' => false,
            'background_overlay_color' => '#000000',
            'background_overlay_opacity' => 50,
            'text_color' => '',
            'title_color' => '',
            'signature_font_family' => 'Dancing Script',
            'print_format' => 'A4',
        ];
    }

    public static function setCommunityEnabled(bool $enabled, ?int $tenantId): void
    {
        Setting::set('student_community_enabled', $enabled ? '1' : '0', $tenantId);
    }

    public static function setCommunityUsersCanDeleteOwnPosts(bool $enabled, ?int $tenantId): void
    {
        Setting::set('student_community_users_can_delete_own_posts', $enabled ? '1' : '0', $tenantId);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function setCertificateConfig(array $config, ?int $tenantId): void
    {
        Setting::set('student_certificate', $config, $tenantId);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function setGamificationConfig(array $config, ?int $tenantId): void
    {
        Setting::set('student_gamification', $config, $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJsonSetting(string $key, int $tenantId): array
    {
        $raw = Setting::get($key, null, $tenantId);
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function legacyCommunityEnabledFromProducts(int $tenantId): bool
    {
        foreach (self::memberAreaProductsForTenant($tenantId) as $product) {
            $config = $product->member_area_config ?? [];
            if (! empty($config['community_enabled'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function legacyCertificateFromProducts(int $tenantId): array
    {
        foreach (self::memberAreaProductsForTenant($tenantId) as $product) {
            $cert = ($product->member_area_config ?? [])['certificate'] ?? [];
            if (! empty($cert['enabled']) || ! empty($cert['title'])) {
                return is_array($cert) ? $cert : [];
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function legacyGamificationFromProducts(int $tenantId): array
    {
        foreach (self::memberAreaProductsForTenant($tenantId) as $product) {
            $g = ($product->member_area_config ?? [])['gamification'] ?? [];
            if (! empty($g['enabled']) || ! empty($g['achievements'])) {
                return is_array($g) ? $g : [];
            }
        }

        return [];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private static function memberAreaProductsForTenant(int $tenantId)
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->orderBy('name')
            ->get();
    }
}
