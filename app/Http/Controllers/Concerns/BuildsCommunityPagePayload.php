<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MemberCommunityPage;
use App\Services\StorageService;

trait BuildsCommunityPagePayload
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function communityPagesPayloadForTenant(int $tenantId): array
    {
        return MemberCommunityPage::query()
            ->forTenant($tenantId)
            ->orderBy('position')
            ->get()
            ->map(fn (MemberCommunityPage $p) => $this->communityPageToArray($p, $tenantId))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function communityPageToArray(MemberCommunityPage $p, int $tenantId): array
    {
        $storage = new StorageService($tenantId);

        return [
            'id' => $p->id,
            'title' => $p->title,
            'icon' => $p->icon,
            'slug' => $p->slug,
            'banner' => $p->banner,
            'banner_url' => $p->banner ? $storage->url($p->banner) : null,
            'position' => $p->position,
            'is_public_posting' => $p->is_public_posting,
            'is_default' => (bool) ($p->is_default ?? false),
        ];
    }
}
