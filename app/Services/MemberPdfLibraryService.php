<?php

namespace App\Services;

use App\Models\MemberLesson;
use App\Models\MemberPdfLibraryFolder;
use App\Models\MemberPdfLibraryItem;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberPdfLibraryService
{
    public function __construct(
        private readonly StorageService $storage
    ) {}

    public function store(
        UploadedFile $file,
        int $tenantId,
        ?string $productId,
        int $userId,
        ?int $folderId = null
    ): MemberPdfLibraryItem {
        if ($folderId !== null) {
            $this->findFolderForTenant($tenantId, $folderId);
        }

        $originalName = $file->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName) ?: 'documento';
        $filename = $safeBase.'-'.Str::lower(Str::random(8)).'.pdf';
        $directory = 'member-pdf-library/'.$tenantId;

        $path = $this->storage->putFileAs($directory, $file, $filename);

        return MemberPdfLibraryItem::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'folder_id' => $folderId,
            'uploaded_by' => $userId,
            'name' => mb_substr($originalName, 0, 255),
            'storage_path' => $path,
            'file_size' => (int) $file->getSize(),
            'mime' => $file->getMimeType() ?: 'application/pdf',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, MemberPdfLibraryFolder>
     */
    public function listFolders(int $tenantId)
    {
        return MemberPdfLibraryFolder::query()
            ->forTenant($tenantId)
            ->withCount('items')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function createFolder(int $tenantId, string $name): MemberPdfLibraryFolder
    {
        $name = $this->normalizeFolderName($name);

        if (MemberPdfLibraryFolder::query()->forTenant($tenantId)->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Já existe uma pasta com este nome.'],
            ]);
        }

        $maxPosition = (int) MemberPdfLibraryFolder::query()->forTenant($tenantId)->max('position');

        return MemberPdfLibraryFolder::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'position' => $maxPosition + 1,
        ]);
    }

    public function renameFolder(MemberPdfLibraryFolder $folder, string $name): MemberPdfLibraryFolder
    {
        $name = $this->normalizeFolderName($name);

        $exists = MemberPdfLibraryFolder::query()
            ->forTenant((int) $folder->tenant_id)
            ->where('name', $name)
            ->where('id', '!=', $folder->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Já existe uma pasta com este nome.'],
            ]);
        }

        $folder->update(['name' => $name]);

        return $folder->fresh();
    }

    public function deleteFolder(MemberPdfLibraryFolder $folder): void
    {
        if ($folder->items()->count() > 0) {
            abort(409, 'A pasta não está vazia. Mova ou remova os PDFs antes de excluir a pasta.');
        }

        $folder->delete();
    }

    public function moveItem(MemberPdfLibraryItem $item, ?int $folderId): MemberPdfLibraryItem
    {
        if ($folderId !== null) {
            $this->findFolderForTenant((int) $item->tenant_id, $folderId);
        }

        $item->update(['folder_id' => $folderId]);

        return $item->fresh();
    }

    public function findFolderForTenant(int $tenantId, int $folderId): MemberPdfLibraryFolder
    {
        $folder = MemberPdfLibraryFolder::query()
            ->forTenant($tenantId)
            ->where('id', $folderId)
            ->first();

        if (! $folder) {
            abort(404);
        }

        return $folder;
    }

    /**
     * @return LengthAwarePaginator<int, MemberPdfLibraryItem>
     */
    public function paginateForTenant(
        int $tenantId,
        int $perPage = 24,
        ?string $search = null,
        ?string $productId = null,
        ?int $folderId = null
    ): LengthAwarePaginator {
        $query = MemberPdfLibraryItem::query()
            ->forTenant($tenantId)
            ->orderByDesc('created_at');

        if ($folderId === null) {
            $query->whereNull('folder_id');
        } else {
            $query->where('folder_id', $folderId);
        }

        if ($search !== null && trim($search) !== '') {
            $q = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where('name', 'like', $q);
        }

        if ($productId !== null && trim($productId) !== '') {
            $query->where('product_id', trim($productId));
        }

        return $query->paginate($perPage);
    }

    public function publicUrl(MemberPdfLibraryItem $item): string
    {
        return $this->storage->url($item->storage_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function folderToPublicPayload(MemberPdfLibraryFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'position' => $folder->position,
            'items_count' => (int) ($folder->items_count ?? $folder->items()->count()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(MemberPdfLibraryItem $item, ?int $usageCount = null): array
    {
        $usage = $usageCount ?? $this->usageCount($item);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'url' => $this->publicUrl($item),
            'storage_path' => $item->storage_path,
            'file_size' => $item->file_size,
            'human_size' => $this->humanFileSize($item->file_size),
            'product_id' => $item->product_id,
            'folder_id' => $item->folder_id,
            'usage_count' => $usage,
            'created_at' => $item->created_at?->toIso8601String(),
            'created_at_formatted' => $item->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function usageCount(MemberPdfLibraryItem $item): int
    {
        $publicUrl = $this->publicUrl($item);
        $productIds = Product::forTenant($item->tenant_id)->pluck('id')->all();
        if ($productIds === []) {
            return 0;
        }

        $count = 0;
        MemberLesson::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('content_files')
            ->select(['id', 'content_files'])
            ->chunkById(100, function ($lessons) use ($item, $publicUrl, &$count): void {
                foreach ($lessons as $lesson) {
                    if ($this->lessonUsesLibraryItem($lesson->content_files, $item->id, $publicUrl)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * @param  mixed  $contentFiles
     */
    private function lessonUsesLibraryItem(mixed $contentFiles, int $libraryItemId, string $publicUrl): bool
    {
        if (! is_array($contentFiles)) {
            return false;
        }

        foreach ($contentFiles as $file) {
            if (! is_array($file)) {
                continue;
            }
            if (isset($file['library_item_id']) && (int) $file['library_item_id'] === $libraryItemId) {
                return true;
            }
            if (trim((string) ($file['url'] ?? '')) === $publicUrl) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function deleteIfUnused(MemberPdfLibraryItem $item): void
    {
        if ($this->usageCount($item) > 0) {
            abort(409, 'Este PDF ainda está em uso em uma ou mais aulas. Remova-o das aulas antes de excluir da biblioteca.');
        }

        $item->delete();
    }

    private function normalizeFolderName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Informe um nome para a pasta.'],
            ]);
        }

        return mb_substr($name, 0, 120);
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
