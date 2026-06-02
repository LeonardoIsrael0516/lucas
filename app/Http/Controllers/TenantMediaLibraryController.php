<?php

namespace App\Http\Controllers;

use App\Models\MemberPdfLibraryFolder;
use App\Models\MemberPdfLibraryItem;
use App\Models\Product;
use App\Services\MemberPdfLibraryService;
use App\Services\StorageService;
use App\Services\TeamAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class TenantMediaLibraryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeTenantLibrary();

        $tenantId = (int) auth()->user()->tenant_id;

        return Inertia::render('Biblioteca/Index', [
            'pageTitle' => 'Biblioteca de mídia',
            'layoutFullWidth' => true,
            'tenant_products' => $this->tenantProductsPayload($tenantId),
            'upload_limits' => [
                'pdf_max_mb' => (int) max(1, floor((int) config('media_library.pdf_max_kb', 51200) / 1024)),
                'image_max_mb' => (int) max(1, floor((int) config('media_library.image_max_kb', 10240) / 1024)),
            ],
        ]);
    }

    public function listItems(Request $request): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $library = $this->library();
        $tenantId = (int) auth()->user()->tenant_id;

        $perPage = min(48, max(12, (int) $request->query('per_page', 24)));
        $search = $request->query('q');
        $filterProductId = $request->query('product_id');
        $mediaType = $request->query('media_type');
        $folderId = $this->parseFolderIdQuery($request->query('folder_id'));

        $currentFolder = null;
        $breadcrumb = [];

        if ($folderId !== null) {
            $currentFolder = $library->findFolderForTenant($tenantId, $folderId);
            $breadcrumb = $library->breadcrumbForFolder($currentFolder);
        }

        $paginator = $library->paginateForTenant(
            $tenantId,
            $perPage,
            is_string($search) ? $search : null,
            is_string($filterProductId) ? $filterProductId : null,
            $folderId,
            is_string($mediaType) ? $mediaType : null
        );

        $items = $paginator->getCollection()
            ->map(fn (MemberPdfLibraryItem $item) => $library->toPublicPayload($item))
            ->values()
            ->all();

        $childFolders = $library->listFolders($tenantId, $folderId)
            ->map(fn (MemberPdfLibraryFolder $f) => $library->folderToPublicPayload($f))
            ->values()
            ->all();

        $allFolders = $library->listAllFoldersFlat($tenantId)
            ->map(fn (MemberPdfLibraryFolder $f) => $library->folderToPublicPayload($f, true))
            ->values()
            ->all();

        return response()->json([
            'child_folders' => $childFolders,
            'folders' => $childFolders,
            'all_folders' => $allFolders,
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'breadcrumb' => $breadcrumb,
            'current_folder' => $currentFolder
                ? $library->folderToPublicPayload($currentFolder)
                : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $library = $this->library();
        $tenantId = (int) auth()->user()->tenant_id;

        /** @var UploadedFile|null $file */
        $file = $request->file('file');
        $mime = $file instanceof UploadedFile
            ? MemberPdfLibraryItem::resolveUploadMime($file)
            : 'application/octet-stream';
        $maxKb = $library->maxKbForMime($mime);

        $validated = $request->validate([
            'file' => $library->storeFileValidationRules($maxKb),
            'folder_id' => ['nullable', 'integer', 'exists:member_pdf_library_folders,id'],
            'product_id' => ['nullable', 'string'],
        ], [
            'file.required' => 'Nenhum arquivo enviado.',
            'file.max' => 'O arquivo deve ter no máximo '.(int) max(1, floor($maxKb / 1024)).' MB.',
        ]);

        $folderId = isset($validated['folder_id']) ? (int) $validated['folder_id'] : null;
        $productId = isset($validated['product_id']) && trim($validated['product_id']) !== ''
            ? trim($validated['product_id'])
            : null;

        if ($productId !== null) {
            $this->assertProductInTenant($productId, $tenantId);
        }

        $item = $library->store(
            $validated['file'],
            $tenantId,
            $productId,
            (int) $request->user()->id,
            $folderId
        );

        $payload = $library->toPublicPayload($item, 0);

        return response()->json([
            'message' => 'Arquivo adicionado à biblioteca.',
            'item' => $payload,
            'url' => $payload['url'],
            'path' => $item->storage_path,
            'library_item_id' => $item->id,
        ], 201);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $library = $this->library();
        $tenantId = (int) auth()->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:member_pdf_library_folders,id'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        $folder = $library->createFolder($tenantId, $validated['name'], $parentId);

        return response()->json([
            'message' => 'Pasta criada.',
            'folder' => $library->folderToPublicPayload($folder->loadCount(['items', 'children'])),
        ], 201);
    }

    public function updateFolder(Request $request, MemberPdfLibraryFolder $folder): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $this->authorizeFolder($folder);
        $library = $this->library();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder = $library->renameFolder($folder, $validated['name']);

        return response()->json([
            'message' => 'Pasta renomeada.',
            'folder' => $library->folderToPublicPayload($folder->loadCount(['items', 'children'])),
        ]);
    }

    public function destroyFolder(Request $request, MemberPdfLibraryFolder $folder): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $this->authorizeFolder($folder);
        $library = $this->library();

        $library->deleteFolder($folder);

        return response()->json(['message' => 'Pasta removida.']);
    }

    public function moveItem(Request $request, MemberPdfLibraryItem $item): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $this->authorizeLibraryItem($item);
        $library = $this->library();

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:member_pdf_library_folders,id'],
        ]);

        $folderId = array_key_exists('folder_id', $validated) && $validated['folder_id'] !== null
            ? (int) $validated['folder_id']
            : null;

        $item = $library->moveItem($item, $folderId);

        return response()->json([
            'message' => 'Arquivo movido.',
            'item' => $library->toPublicPayload($item),
        ]);
    }

    public function replace(Request $request, MemberPdfLibraryItem $item): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $this->authorizeLibraryItem($item);
        $library = $this->library();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.(int) config('media_library.pdf_max_kb', 51200)],
            'name' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Nenhum PDF enviado.',
            'file.mimes' => 'Envie um arquivo PDF.',
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];

        $result = $library->replacePdfItem($item, $file, $validated['name'] ?? null);
        $item = $result['item']->fresh();
        $payload = $library->toPublicPayload($item, $library->usageCount($item));

        $lessonsUpdated = (int) $result['lessons_updated'];

        return response()->json([
            'message' => $lessonsUpdated > 0
                ? "PDF atualizado. {$lessonsUpdated} aula(s) passaram a usar o novo arquivo."
                : 'PDF atualizado na biblioteca.',
            'item' => $payload,
            'url' => $payload['url'],
            'library_item_id' => $item->id,
            'lessons_updated' => $lessonsUpdated,
        ]);
    }

    public function destroy(Request $request, MemberPdfLibraryItem $item): JsonResponse
    {
        $this->authorizeTenantLibrary();
        $this->authorizeLibraryItem($item);

        $this->library()->deleteIfUnused($item);

        return response()->json(['message' => 'Arquivo removido da biblioteca.']);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function tenantProductsPayload(int $tenantId): array
    {
        $query = Product::forTenant($tenantId)->orderBy('name');

        if (auth()->user()->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            if ($allowed !== []) {
                $query->whereIn('id', $allowed);
            }
        }

        return $query->get(['id', 'name'])
            ->map(fn (Product $p) => ['id' => (string) $p->id, 'name' => $p->name])
            ->values()
            ->all();
    }

    private function parseFolderIdQuery(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }

    private function authorizeTenantLibrary(): void
    {
        $user = auth()->user();
        if (! $user || ! app(TeamAccessService::class)->can($user, 'produtos.view')) {
            abort(403);
        }
    }

    private function authorizeLibraryItem(MemberPdfLibraryItem $item): void
    {
        if ((int) $item->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(404);
        }
    }

    private function authorizeFolder(MemberPdfLibraryFolder $folder): void
    {
        if ((int) $folder->tenant_id !== (int) auth()->user()->tenant_id) {
            abort(404);
        }
    }

    private function assertProductInTenant(string $productId, int $tenantId): void
    {
        $exists = Product::forTenant($tenantId)->where('id', $productId)->exists();
        if (! $exists) {
            abort(404);
        }

        if (auth()->user()->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            if (! in_array($productId, $allowed, true)) {
                abort(403);
            }
        }
    }

    private function library(): MemberPdfLibraryService
    {
        return new MemberPdfLibraryService(new StorageService((int) auth()->user()->tenant_id));
    }
}
