<?php

namespace App\Support;

use App\Models\Setting;

final class RefundSettings
{
    /** @var list<int> */
    public const ALLOWED_DAYS = [7, 15, 30, 60, 90];

    public const DEFAULT_DAYS = 7;

    public static function enabled(?int $tenantId): bool
    {
        if ($tenantId === null) {
            return false;
        }
        $v = Setting::get('refund_enabled', '0', $tenantId);

        return $v === '1' || $v === 1 || $v === true;
    }

    public static function days(?int $tenantId): int
    {
        if ($tenantId === null) {
            return self::DEFAULT_DAYS;
        }
        $raw = (int) Setting::get('refund_days', (string) self::DEFAULT_DAYS, $tenantId);

        return in_array($raw, self::ALLOWED_DAYS, true) ? $raw : self::DEFAULT_DAYS;
    }

    public static function setEnabled(bool $enabled, ?int $tenantId): void
    {
        Setting::set('refund_enabled', $enabled ? '1' : '0', $tenantId);
    }

    public static function setDays(int $days, ?int $tenantId): void
    {
        if (! in_array($days, self::ALLOWED_DAYS, true)) {
            $days = self::DEFAULT_DAYS;
        }
        Setting::set('refund_days', (string) $days, $tenantId);
    }

    /**
     * @return array{enabled: bool, days: int, allowed_days: list<int>}
     */
    public static function payload(?int $tenantId): array
    {
        return [
            'enabled' => self::enabled($tenantId),
            'days' => self::days($tenantId),
            'allowed_days' => self::ALLOWED_DAYS,
        ];
    }
}
