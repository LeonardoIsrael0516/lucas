<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property int|null $tenant_id */

class MemberCommunityPage extends Model
{
    protected $fillable = ['tenant_id', 'product_id', 'title', 'icon', 'slug', 'banner', 'position', 'is_public_posting', 'is_default'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_public_posting' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MemberCommunityPage $page): void {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }

        $productIds = Product::query()->where('tenant_id', $tenantId)->pluck('id');

        return $query->where(function ($q) use ($tenantId, $productIds) {
            $q->where('tenant_id', $tenantId);
            if ($productIds->isNotEmpty()) {
                $q->orWhereIn('product_id', $productIds);
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(MemberCommunityPost::class, 'member_community_page_id')->latest();
    }
}
