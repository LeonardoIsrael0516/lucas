<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberPdfLibraryItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'folder_id',
        'uploaded_by',
        'name',
        'storage_path',
        'file_size',
        'mime',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MemberPdfLibraryFolder::class, 'folder_id');
    }
}
