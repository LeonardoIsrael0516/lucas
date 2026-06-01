<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\StorageService;
use App\Services\StorageUrlNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class StorageMigrateController extends Controller
{
    private const STREAM_THRESHOLD_BYTES = 5 * 1024 * 1024; // 5 MB

    private const MAX_ERRORS_RETURNED = 10;

    private const DEFAULT_BATCH_SIZE = 20;

    private const MAX_BATCH_SIZE = 40;

    private const CACHE_TTL_SECONDS = 7200;

    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $cloudMode = (bool) config('getfy.cloud_mode', false);
        $r2EnvKey = (string) env('R2_ACCESS_KEY_ID', '');
        $r2EnvSecret = (string) env('R2_SECRET_ACCESS_KEY', '');
        $r2EnvBucket = (string) env('R2_BUCKET', '');
        $r2EnvEndpoint = (string) env('R2_ENDPOINT', '');
        $r2EnvConfigured = $r2EnvKey !== '' && $r2EnvSecret !== '' && $r2EnvBucket !== '' && $r2EnvEndpoint !== '';

        $provider = Setting::get('storage_provider', null, $tenantId);
        if ($provider === null || $provider === '') {
            $provider = ($cloudMode && $r2EnvConfigured) ? 'r2' : 'local';
        }

        if ($provider === 'local' || $provider === '' || ! in_array($provider, ['s3', 'wasabi', 'r2'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Configure e salve o storage S3 ou R2 antes de usar a migração.',
            ], 422);
        }

        $storageService = new StorageService($tenantId);
        $destinationDisk = $storageService->disk();

        if ($storageService->isLocal()) {
            return response()->json([
                'success' => false,
                'message' => 'O storage atual ainda é o local. Salve as credenciais S3/R2 e tente novamente.',
            ], 422);
        }

        if ($request->boolean('finalize')) {
            return $this->finalizeMigration($tenantId);
        }

        if ($request->boolean('cancel')) {
            Cache::forget($this->cacheKey($tenantId));

            return response()->json([
                'success' => true,
                'message' => 'Migração cancelada.',
            ]);
        }

        $offset = max(0, (int) $request->input('offset', 0));
        $batchSize = min(self::MAX_BATCH_SIZE, max(5, (int) $request->input('batch_size', self::DEFAULT_BATCH_SIZE)));
        $reset = $request->boolean('reset', $offset === 0);

        return $this->migrateBatch($tenantId, $offset, $batchSize, $reset, $destinationDisk);
    }

    private function migrateBatch(
        int $tenantId,
        int $offset,
        int $batchSize,
        bool $reset,
        $destinationDisk
    ): JsonResponse {
        $cacheKey = $this->cacheKey($tenantId);

        if ($reset) {
            Cache::forget($cacheKey);
        }

        $paths = Cache::get($cacheKey);
        if (! is_array($paths)) {
            set_time_limit(120);
            $paths = Storage::disk('public')->allFiles('');
            Cache::put($cacheKey, $paths, self::CACHE_TTL_SECONDS);
        }

        $total = count($paths);
        if ($total === 0) {
            Cache::forget($cacheKey);

            return $this->finalizeMigration($tenantId, 0, 0);
        }

        $slice = array_slice($paths, $offset, $batchSize);
        $localDisk = Storage::disk('public');

        set_time_limit(max(120, $batchSize * 30));
        ignore_user_abort(true);

        $transferred = 0;
        $failed = 0;
        $errors = [];

        foreach ($slice as $path) {
            try {
                if (! $localDisk->exists($path)) {
                    $transferred++;

                    continue;
                }

                if ($destinationDisk->exists($path)) {
                    $transferred++;

                    continue;
                }

                $size = $localDisk->size($path);
                if ($size !== false && $size >= self::STREAM_THRESHOLD_BYTES) {
                    $stream = $localDisk->readStream($path);
                    if ($stream === false) {
                        throw new \RuntimeException('Falha ao abrir stream do arquivo.');
                    }
                    $written = $destinationDisk->writeStream($path, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if (! $written) {
                        throw new \RuntimeException('Falha ao gravar stream no destino.');
                    }
                } else {
                    $content = $localDisk->get($path);
                    $destinationDisk->put($path, $content);
                }
                $transferred++;
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < self::MAX_ERRORS_RETURNED) {
                    $errors[] = ['path' => $path, 'message' => $e->getMessage()];
                }
            }
        }

        $nextOffset = $offset + count($slice);
        $done = $nextOffset >= $total;

        if ($done) {
            Cache::forget($cacheKey);

            return $this->finalizeMigration($tenantId, $transferred, $failed, $errors, $total);
        }

        return response()->json([
            'success' => true,
            'done' => false,
            'transferred' => $transferred,
            'failed' => $failed,
            'total' => $total,
            'processed' => $nextOffset,
            'next_offset' => $nextOffset,
            'message' => "Transferindo arquivos… {$nextOffset} de {$total}",
            'errors' => $errors,
        ]);
    }

    /**
     * @param  array<int, array{path: string, message: string}>  $errors
     */
    private function finalizeMigration(
        int $tenantId,
        int $batchTransferred = 0,
        int $batchFailed = 0,
        array $errors = [],
        ?int $total = null
    ): JsonResponse {
        set_time_limit(300);

        $normalized = (new StorageUrlNormalizer)->normalizeAll();

        $message = $total !== null
            ? ($batchFailed === 0
                ? "{$batchTransferred} arquivo(s) transferido(s) com sucesso."
                : "{$batchTransferred} arquivo(s) transferido(s), {$batchFailed} falha(s).")
            : 'Nenhum arquivo local para transferir.';

        if ($normalized['updated'] > 0) {
            $message .= ' '.$normalized['updated'].' referência(s) no banco atualizada(s) para o novo storage.';
        }

        Cache::forget($this->cacheKey($tenantId));

        return response()->json([
            'success' => true,
            'done' => true,
            'transferred' => $batchTransferred,
            'failed' => $batchFailed,
            'total' => $total ?? 0,
            'message' => $message,
            'errors' => $errors,
            'normalized' => $normalized['updated'],
            'normalized_details' => $normalized['details'],
        ]);
    }

    private function cacheKey(int $tenantId): string
    {
        return 'storage_migrate_files:'.$tenantId;
    }
}
