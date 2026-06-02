<?php

namespace App\Services;

use App\Models\MemberLesson;
use App\Models\MemberPdfLibraryFolder;
use App\Models\MemberPdfLibraryItem;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $extension = $file->getClientOriginalExtension() ?: $this->extensionFromMime($mime);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName) ?: 'arquivo';
        $filename = $safeBase.'-'.Str::lower(Str::random(8)).'.'.ltrim($extension, '.');
        $directory = 'member-pdf-library/'.$tenantId;

        $path = $this->storage->putFileAs($directory, $file, $filename);

        return $this->registerFromStoredFile(
            $path,
            $originalName,
            $mime,
            (int) $file->getSize(),
            $tenantId,
            $productId,
            $userId,
            $folderId
        );
    }

    public function registerFromStoredFile(
        string $storagePath,
        string $originalName,
        string $mime,
        int $fileSize,
        int $tenantId,
        ?string $productId,
        ?int $userId,
        ?int $folderId = null
    ): MemberPdfLibraryItem {
        if ($folderId !== null) {
            $this->findFolderForTenant($tenantId, $folderId);
        }

        $existing = MemberPdfLibraryItem::query()
            ->forTenant($tenantId)
            ->where('storage_path', $storagePath)
            ->first();

        if ($existing) {
            return $existing;
        }

        $mediaType = MemberPdfLibraryItem::resolveMediaType($mime);

        return MemberPdfLibraryItem::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'folder_id' => $folderId,
            'uploaded_by' => $userId,
            'name' => mb_substr($originalName, 0, 255),
            'storage_path' => $storagePath,
            'file_size' => $fileSize,
            'mime' => mb_substr($mime, 0, 64),
            'media_type' => $mediaType,
        ]);
    }

    /**
     * @return Collection<int, MemberPdfLibraryFolder>
     */
    public function listAllFoldersFlat(int $tenantId): Collection
    {
        return MemberPdfLibraryFolder::query()
            ->forTenant($tenantId)
            ->withCount('items')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, MemberPdfLibraryFolder>
     */
    public function listFolders(int $tenantId, ?int $parentId = null): Collection
    {
        $query = MemberPdfLibraryFolder::query()
            ->forTenant($tenantId)
            ->withCount(['items', 'children'])
            ->orderBy('position')
            ->orderBy('name');

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        return $query->get();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function breadcrumbForFolder(MemberPdfLibraryFolder $folder): array
    {
        $trail = [];
        $current = $folder;

        while ($current) {
            array_unshift($trail, [
                'id' => $current->id,
                'name' => $current->name,
            ]);
            if ($current->parent_id === null) {
                break;
            }
            $current = MemberPdfLibraryFolder::query()
                ->forTenant((int) $current->tenant_id)
                ->where('id', $current->parent_id)
                ->first();
            if (! $current) {
                break;
            }
        }

        return $trail;
    }

    public function createFolder(int $tenantId, string $name, ?int $parentId = null): MemberPdfLibraryFolder
    {
        $name = $this->normalizeFolderName($name);

        if ($parentId !== null) {
            $this->findFolderForTenant($tenantId, $parentId);
        }

        $existsQuery = MemberPdfLibraryFolder::query()
            ->forTenant($tenantId)
            ->where('name', $name);

        if ($parentId === null) {
            $existsQuery->whereNull('parent_id');
        } else {
            $existsQuery->where('parent_id', $parentId);
        }

        if ($existsQuery->exists()) {
            throw ValidationException::withMessages([
                'name' => ['Já existe uma pasta com este nome neste nível.'],
            ]);
        }

        $maxPosition = (int) MemberPdfLibraryFolder::query()
            ->forTenant($tenantId)
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'))
            ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->max('position');

        return MemberPdfLibraryFolder::create([
            'tenant_id' => $tenantId,
            'parent_id' => $parentId,
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
            ->when($folder->parent_id === null, fn ($q) => $q->whereNull('parent_id'))
            ->when($folder->parent_id !== null, fn ($q) => $q->where('parent_id', $folder->parent_id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Já existe uma pasta com este nome neste nível.'],
            ]);
        }

        $folder->update(['name' => $name]);

        return $folder->fresh();
    }

    public function deleteFolder(MemberPdfLibraryFolder $folder): void
    {
        if ($folder->items()->count() > 0) {
            abort(409, 'A pasta não está vazia. Mova ou remova os arquivos antes de excluir a pasta.');
        }

        if ($folder->children()->count() > 0) {
            abort(409, 'A pasta contém subpastas. Remova ou mova as subpastas antes de excluir.');
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
        ?int $folderId = null,
        ?string $mediaType = null
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

        if ($mediaType !== null && trim($mediaType) !== '' && strtolower(trim($mediaType)) !== 'all') {
            $query->where('media_type', strtolower(trim($mediaType)));
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
    public function folderToPublicPayload(MemberPdfLibraryFolder $folder, bool $withPathLabel = false): array
    {
        $payload = [
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
            'position' => $folder->position,
            'items_count' => (int) ($folder->items_count ?? $folder->items()->count()),
            'children_count' => (int) ($folder->children_count ?? $folder->children()->count()),
        ];

        if ($withPathLabel) {
            $names = array_column($this->breadcrumbForFolder($folder), 'name');
            $payload['path_label'] = implode(' / ', $names);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(MemberPdfLibraryItem $item, ?int $usageCount = null): array
    {
        $usage = $usageCount ?? $this->usageCount($item);
        $mediaType = $item->media_type ?: MemberPdfLibraryItem::resolveMediaType($item->mime);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'url' => $this->publicUrl($item),
            'storage_path' => $item->storage_path,
            'file_size' => $item->file_size,
            'human_size' => $this->humanFileSize($item->file_size),
            'mime' => $item->mime,
            'media_type' => $mediaType,
            'is_image' => $mediaType === MemberPdfLibraryItem::TYPE_IMAGE,
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
            ->select(['id', 'content_files', 'attachment_files'])
            ->chunkById(100, function ($lessons) use ($item, $publicUrl, &$count): void {
                foreach ($lessons as $lesson) {
                    if ($this->lessonUsesLibraryItem($lesson->content_files, $item->id, $publicUrl)) {
                        $count++;

                        continue;
                    }
                    if ($this->lessonUsesLibraryItem($lesson->attachment_files, $item->id, $publicUrl)) {
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
            abort(409, 'Este arquivo ainda está em uso em uma ou mais aulas. Remova-o das aulas antes de excluir da biblioteca.');
        }

        $item->delete();
    }

    /**
     * @return array<int, string>
     */
    public function storeMimetypesRule(): array
    {
        $mimes = config('media_library.store_mimetypes', []);

        return ['mimetypes:'.implode(',', $mimes)];
    }

    public function maxKbForMime(string $mime): int
    {
        $type = MemberPdfLibraryItem::resolveMediaType($mime);

        return match ($type) {
            MemberPdfLibraryItem::TYPE_IMAGE => (int) config('media_library.image_max_kb', 10240),
            MemberPdfLibraryItem::TYPE_PDF => (int) config('media_library.pdf_max_kb', 51200),
            MemberPdfLibraryItem::TYPE_ARCHIVE => (int) config('media_library.archive_max_kb', 51200),
            MemberPdfLibraryItem::TYPE_DOCUMENT => (int) config('media_library.document_max_kb', 51200),
            default => (int) config('media_library.document_max_kb', 51200),
        };
    }

    private function extensionFromMime(string $mime): string
    {
        return match (MemberPdfLibraryItem::resolveMediaType($mime)) {
            MemberPdfLibraryItem::TYPE_PDF => 'pdf',
            MemberPdfLibraryItem::TYPE_IMAGE => 'jpg',
            MemberPdfLibraryItem::TYPE_ARCHIVE => 'zip',
            default => 'bin',
        };
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
