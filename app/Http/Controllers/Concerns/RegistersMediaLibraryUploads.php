<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MemberPdfLibraryItem;
use App\Services\MemberPdfLibraryService;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

trait RegistersMediaLibraryUploads
{
    protected function registerMediaLibraryUpload(
        string $storagePath,
        UploadedFile $file,
        int $tenantId,
        ?string $productId = null,
        ?int $userId = null
    ): MemberPdfLibraryItem {
        $library = new MemberPdfLibraryService(new StorageService($tenantId));

        return $library->registerFromStoredFile(
            $storagePath,
            $file->getClientOriginalName() ?: basename($storagePath),
            $file->getMimeType() ?: 'application/octet-stream',
            (int) $file->getSize(),
            $tenantId,
            $productId,
            $userId
        );
    }
}
