<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberPdfLibraryItem extends Model
{
    use SoftDeletes;

    public const TYPE_IMAGE = 'image';

    public const TYPE_PDF = 'pdf';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_ARCHIVE = 'archive';

    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'folder_id',
        'uploaded_by',
        'name',
        'storage_path',
        'file_size',
        'mime',
        'media_type',
    ];

    public static function resolveMediaType(string $mime): string
    {
        $mime = strtolower(trim($mime));

        if (str_starts_with($mime, 'image/')) {
            return self::TYPE_IMAGE;
        }

        if ($mime === 'application/pdf') {
            return self::TYPE_PDF;
        }

        if (in_array($mime, [
            'application/zip',
            'application/x-zip-compressed',
        ], true)) {
            return self::TYPE_ARCHIVE;
        }

        if (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
        ], true)) {
            return self::TYPE_DOCUMENT;
        }

        return self::TYPE_OTHER;
    }

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
