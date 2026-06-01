<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberPdfLibraryFolder extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MemberPdfLibraryItem::class, 'folder_id');
    }
}
