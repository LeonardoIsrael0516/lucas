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

class MemberPdfLibraryController extends Controller
{
    public function index(Request $request, Product $produto): JsonResponse
    {
        $this->authorizeProduct($produto);
        $library = $this->libraryForProduct($produto);
        $tenantId = (int) $produto->tenant_id;

        $perPage = min(48, max(12, (int) $request->query('per_page', 24)));
        $search = $request->query('q');
        $filterProductId = $request->query('product_id');
        $folderId = $this->parseFolderIdQuery($request->query('folder_id'));

        if ($folderId !== null) {
            $currentFolder = $library->findFolderForTenant($tenantId, $folderId);
        }

        $paginator = $library->paginateForTenant(
            $tenantId,
            $perPage,
            is_string($search) ? $search : null,
            is_string($filterProductId) ? $filterProductId : null,
            $folderId
        );

        $items = $paginator->getCollection()
            ->map(fn (MemberPdfLibraryItem $item) => $library->toPublicPayload($item))
            ->values()
            ->all();

        $allFolders = $library->listFolders($tenantId)
            ->map(fn (MemberPdfLibraryFolder $f) => $library->folderToPublicPayload($f))
            ->values()
            ->all();

        return response()->json([
            'folders' => $allFolders,
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'current_folder' => isset($currentFolder)
                ? $library->folderToPublicPayload($currentFolder)
                : null,
        ]);
    }

    public function store(Request $request, Product $produto): JsonResponse
    {
        $this->authorizeProduct($produto);
        $library = $this->libraryForProduct($produto);

        $maxKb = (int) config('member_builder_uploads.pdf_max_kb', 51200);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.$maxKb],
            'folder_id' => ['nullable', 'integer', 'exists:member_pdf_library_folders,id'],
        ], [
            'file.required' => 'Nenhum arquivo enviado.',
            'file.mimetypes' => 'O arquivo deve ser um material em formato PDF.',
            'file.max' => 'O PDF deve ter no máximo '.(int) max(1, floor($maxKb / 1024)).' MB.',
        ]);

        $folderId = isset($validated['folder_id']) ? (int) $validated['folder_id'] : null;

        $user = $request->user();
        $item = $library->store(
            $validated['file'],
            (int) $produto->tenant_id,
            (string) $produto->id,
            (int) $user->id,
            $folderId
        );

        $payload = $library->toPublicPayload($item, 0);

        return response()->json([
            'message' => 'PDF adicionado à biblioteca.',
            'item' => $payload,
            'url' => $payload['url'],
            'path' => $item->storage_path,
            'library_item_id' => $item->id,
        ], 201);
    }

    public function storeFolder(Request $request, Product $produto): JsonResponse
    {
        $this->authorizeProduct($produto);
        $library = $this->libraryForProduct($produto);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder = $library->createFolder((int) $produto->tenant_id, $validated['name']);

        return response()->json([
            'message' => 'Pasta criada.',
            'folder' => $library->folderToPublicPayload($folder->loadCount('items')),
        ], 201);
    }

    public function updateFolder(Request $request, Product $produto, MemberPdfLibraryFolder $folder): JsonResponse
    {
        $this->authorizeProduct($produto);
        $this->authorizeFolder($produto, $folder);
        $library = $this->libraryForProduct($produto);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder = $library->renameFolder($folder, $validated['name']);

        return response()->json([
            'message' => 'Pasta renomeada.',
            'folder' => $library->folderToPublicPayload($folder->loadCount('items')),
        ]);
    }

    public function destroyFolder(Request $request, Product $produto, MemberPdfLibraryFolder $folder): JsonResponse
    {
        $this->authorizeProduct($produto);
        $this->authorizeFolder($produto, $folder);
        $library = $this->libraryForProduct($produto);

        $library->deleteFolder($folder);

        return response()->json(['message' => 'Pasta removida.']);
    }

    public function moveItem(Request $request, Product $produto, MemberPdfLibraryItem $item): JsonResponse
    {
        $this->authorizeProduct($produto);
        $this->authorizeLibraryItem($produto, $item);
        $library = $this->libraryForProduct($produto);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:member_pdf_library_folders,id'],
        ]);

        $folderId = array_key_exists('folder_id', $validated) && $validated['folder_id'] !== null
            ? (int) $validated['folder_id']
            : null;

        $item = $library->moveItem($item, $folderId);

        return response()->json([
            'message' => 'PDF movido.',
            'item' => $library->toPublicPayload($item),
        ]);
    }

    public function destroy(Request $request, Product $produto, MemberPdfLibraryItem $item): JsonResponse
    {
        $this->authorizeProduct($produto);
        $this->authorizeLibraryItem($produto, $item);

        $this->libraryForProduct($produto)->deleteIfUnused($item);

        return response()->json(['message' => 'PDF removido da biblioteca.']);
    }

    private function parseFolderIdQuery(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }

    private function authorizeProduct(Product $produto): void
    {
        $tenantId = auth()->user()->tenant_id;
        if ($produto->tenant_id !== $tenantId) {
            abort(403);
        }

        if (auth()->user()->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            if (! in_array($produto->id, $allowed, true)) {
                abort(403);
            }
        }
    }

    private function authorizeLibraryItem(Product $produto, MemberPdfLibraryItem $item): void
    {
        if ((int) $item->tenant_id !== (int) $produto->tenant_id) {
            abort(404);
        }
    }

    private function authorizeFolder(Product $produto, MemberPdfLibraryFolder $folder): void
    {
        if ((int) $folder->tenant_id !== (int) $produto->tenant_id) {
            abort(404);
        }
    }

    private function libraryForProduct(Product $produto): MemberPdfLibraryService
    {
        return new MemberPdfLibraryService(new StorageService($produto->tenant_id));
    }
}
