<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Setting;

trait SharesStudentSupportProps
{
    /**
     * @return array{suporte_href: string|null, support_whatsapp: array{enabled: bool, url: string}}
     */
    protected function studentSupportPayload(?int $tenantId): array
    {
        if (! $tenantId) {
            return [
                'suporte_href' => null,
                'support_whatsapp' => ['enabled' => false, 'url' => ''],
            ];
        }

        $enabled = Setting::get('student_support_enabled', '0', $tenantId) === '1';
        $waEnabled = Setting::get('student_support_whatsapp_enabled', '0', $tenantId) === '1';
        $waUrl = trim((string) Setting::get('student_support_whatsapp_url', '', $tenantId));

        return [
            'suporte_href' => $enabled ? route('student-support.index') : null,
            'support_whatsapp' => [
                'enabled' => $waEnabled && $waUrl !== '',
                'url' => $waUrl,
            ],
        ];
    }
}
