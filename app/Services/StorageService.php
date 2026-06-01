<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageService
{
    private ?int $tenantId = null;

    private ?Filesystem $disk = null;

    private bool $isLocal = true;

    /** URL pública do disco ativo (ex.: https://r2.getfy.cloud ou https://app/storage). */
    private string $publicBaseUrl = '';

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId ?? auth()->user()?->tenant_id;
    }

    /**
     * @return array{configured: bool, key: string, secret: string, bucket: string, endpoint: string, url: string, region: string}
     */
    private function r2EnvConfig(): array
    {
        $key = (string) env('R2_ACCESS_KEY_ID', '');
        $secret = (string) env('R2_SECRET_ACCESS_KEY', '');
        $bucket = (string) env('R2_BUCKET', '');
        $endpoint = (string) env('R2_ENDPOINT', '');
        $url = (string) env('R2_PUBLIC_URL', '');
        $region = (string) env('R2_REGION', 'auto');

        $configured = $key !== '' && $secret !== '' && $bucket !== '' && $endpoint !== '';

        return [
            'configured' => $configured,
            'key' => $key,
            'secret' => $secret,
            'bucket' => $bucket,
            'endpoint' => $endpoint,
            'url' => $url,
            'region' => $region ?: 'auto',
        ];
    }

    /**
     * Get the active storage disk for the current tenant.
     */
    public function disk(): Filesystem
    {
        if ($this->disk !== null) {
            return $this->disk;
        }

        $cloudMode = (bool) config('getfy.cloud_mode', false);
        $r2Env = $this->r2EnvConfig();

        $provider = Setting::get('storage_provider', null, $this->tenantId);
        if ($provider === null || $provider === '') {
            $provider = ($cloudMode && $r2Env['configured']) ? 'r2' : 'local';
        }

        if ($provider === 'local' || empty($provider)) {
            $this->disk = Storage::disk('public');
            $this->isLocal = true;
            $this->publicBaseUrl = rtrim(url('/storage'), '/');

            return $this->disk;
        }

        $key = Setting::get('storage_s3_key', '', $this->tenantId);
        $secretRaw = Setting::get('storage_s3_secret', '', $this->tenantId);
        $secret = '';
        if ($secretRaw) {
            try {
                $secret = Crypt::decryptString($secretRaw);
            } catch (\Throwable) {
                $secret = '';
            }
        }
        $bucket = Setting::get('storage_s3_bucket', '', $this->tenantId);
        $region = Setting::get('storage_s3_region', 'us-east-1', $this->tenantId);
        $endpoint = Setting::get('storage_s3_endpoint', '', $this->tenantId);
        $url = Setting::get('storage_s3_url', '', $this->tenantId);

        $useEnvR2 = $cloudMode
            && $provider === 'r2'
            && $r2Env['configured']
            && trim((string) $key) === ''
            && trim((string) $bucket) === ''
            && trim((string) $endpoint) === ''
            && trim((string) $url) === ''
            && trim((string) $secretRaw) === '';

        if ($useEnvR2) {
            $key = $r2Env['key'];
            $secret = $r2Env['secret'];
            $bucket = $r2Env['bucket'];
            $endpoint = $r2Env['endpoint'];
            $url = $r2Env['url'];
            $region = $r2Env['region'];
        }

        if (empty($key) || empty($secret) || empty($bucket)) {
            $this->disk = Storage::disk('public');
            $this->isLocal = true;
            $this->publicBaseUrl = rtrim(url('/storage'), '/');

            return $this->disk;
        }

        $isR2 = $provider === 'r2' || ($endpoint && str_contains($endpoint, 'r2.cloudflarestorage.com'));
        $regionForConfig = $isR2 ? 'auto' : ($region ?: 'us-east-1');

        $config = [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $regionForConfig,
            'bucket' => $bucket,
            'throw' => false,
            'report' => false,
        ];

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = str_contains($endpoint, 'r2.cloudflarestorage.com')
                || str_contains($endpoint, 'wasabisys.com')
                || str_contains($endpoint, 'digitaloceanspaces.com');
        }

        if ($url) {
            $config['url'] = rtrim($url, '/');
            $this->publicBaseUrl = rtrim($url, '/');
        }

        $this->disk = Storage::build($config);
        $this->isLocal = false;

        return $this->disk;
    }

    /**
     * Whether the current disk is local (public) or remote (S3/R2).
     */
    public function isLocal(): bool
    {
        $this->disk();

        return $this->isLocal;
    }

    /**
     * Store an uploaded file and return the path.
     */
    public function putFile(string $directory, UploadedFile $file, ?string $name = null): string
    {
        $name = $name ?? $file->hashName();

        return $this->disk()->putFileAs($directory, $file, $name);
    }

    /**
     * Store file with putFileAs.
     */
    public function putFileAs(string $directory, UploadedFile $file, string $name): string
    {
        return $this->disk()->putFileAs($directory, $file, $name);
    }

    /**
     * Get the public URL for a stored file.
     */
    public function url(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        $this->disk(); // ensure disk is resolved (sets isLocal)

        if ($this->isLocal) {
            return url('/storage/' . ltrim($path, '/'));
        }

        return $this->disk->url($path);
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return $this->disk()->delete($path);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return $this->disk()->exists($path);
    }

    /**
     * Converte URL pública ou caminho relativo gravado no banco em path do disco (local / R2 / S3).
     */
    public function pathFromStoredUrl(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $stored)) {
            return ltrim(str_replace('\\', '/', $stored), '/');
        }

        $normalizer = app(StorageUrlNormalizer::class);
        if ($normalizer->isLocalStorageUrl($stored)) {
            return $normalizer->toRelativePath($stored);
        }

        $this->disk();
        $base = rtrim($this->publicBaseUrl, '/');
        if ($base !== '' && (str_starts_with($stored, $base.'/') || $stored === $base)) {
            return ltrim(substr($stored, strlen($base)), '/');
        }

        $pathPart = parse_url($stored, PHP_URL_PATH);
        if (is_string($pathPart) && $pathPart !== '') {
            $candidate = ltrim(rawurldecode($pathPart), '/');
            if ($candidate !== '' && $this->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Entrega PDF do storage com suporte a Range (pdf.js) e download forçado.
     */
    public function streamPdfResponse(Request $request, string $relativePath, string $filename, bool $download = false): StreamedResponse
    {
        $this->disk();
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || ! $this->exists($relativePath)) {
            abort(404);
        }

        $filename = $this->sanitizePdfFilename($filename, $relativePath);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"';

        $baseHeaders = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->isLocal) {
            return $this->streamLocalPdfWithRange($request, $relativePath, $baseHeaders);
        }

        return $this->streamRemotePdfWithRange($request, $relativePath, $baseHeaders);
    }

    private function sanitizePdfFilename(string $filename, string $relativePath): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            $filename = basename($relativePath);
        }
        $filename = preg_replace('/[^\w.\-\x{00C0}-\x{024F}]+/u', '_', $filename) ?? 'documento.pdf';
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function streamLocalPdfWithRange(Request $request, string $relativePath, array $headers): StreamedResponse
    {
        $fullPath = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if (! is_file($fullPath)) {
            abort(404);
        }

        $size = filesize($fullPath);
        if ($size === false) {
            abort(500);
        }

        $range = $request->header('Range');
        if (is_string($range) && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            $start = $m[1] !== '' ? (int) $m[1] : 0;
            $end = $m[2] !== '' ? (int) $m[2] : $size - 1;
            $end = min($end, $size - 1);
            if ($start > $end || $start >= $size) {
                return response()->stream(fn () => null, 416, $headers + [
                    'Content-Range' => "bytes */{$size}",
                ]);
            }
            $length = $end - $start + 1;

            return response()->stream(function () use ($fullPath, $start, $length): void {
                $fp = fopen($fullPath, 'rb');
                if ($fp === false) {
                    return;
                }
                fseek($fp, $start);
                $remaining = $length;
                while ($remaining > 0 && ! feof($fp)) {
                    $chunk = fread($fp, min(65536, $remaining));
                    if ($chunk === false) {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
                fclose($fp);
            }, 206, $headers + [
                'Content-Length' => (string) $length,
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
            ]);
        }

        return response()->stream(function () use ($fullPath): void {
            $fp = fopen($fullPath, 'rb');
            if ($fp === false) {
                return;
            }
            fpassthru($fp);
            fclose($fp);
        }, 200, $headers + [
            'Content-Length' => (string) $size,
        ]);
    }

    /**
     * PDF no R2/S3 com suporte a Range (obrigatório para pdf.js).
     *
     * @param  array<string, string>  $headers
     */
    private function streamRemotePdfWithRange(Request $request, string $relativePath, array $headers): StreamedResponse
    {
        $disk = $this->disk();
        try {
            $size = (int) $disk->size($relativePath);
        } catch (\Throwable) {
            abort(404);
        }
        if ($size <= 0) {
            abort(404);
        }

        $rangeHeader = $request->header('Range');
        $range = $this->parseByteRange(is_string($rangeHeader) ? $rangeHeader : null, $size);
        if (is_string($rangeHeader) && preg_match('/^bytes=/i', $rangeHeader) && $range === null) {
            return response()->stream(fn () => null, 416, $headers + [
                'Content-Range' => "bytes */{$size}",
            ]);
        }

        $s3 = $this->s3ObjectTarget($relativePath);

        if ($s3 !== null) {
            if ($range !== null) {
                return $this->streamS3ObjectRange($s3, $range, $size, $headers);
            }

            return $this->streamS3ObjectFull($s3, $size, $headers);
        }

        if ($range !== null) {
            return $this->streamFlysystemRange($disk, $relativePath, $range, $size, $headers);
        }

        return $this->streamFlysystemFull($disk, $relativePath, $size, $headers);
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function parseByteRange(?string $rangeHeader, int $size): ?array
    {
        if (! is_string($rangeHeader) || ! preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $m)) {
            return null;
        }
        $start = $m[1] !== '' ? (int) $m[1] : 0;
        $end = $m[2] !== '' ? (int) $m[2] : $size - 1;
        $end = min($end, $size - 1);
        if ($start > $end || $start >= $size) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{client: S3Client, bucket: string, key: string}|null
     */
    private function s3ObjectTarget(string $relativePath): ?array
    {
        if ($this->isLocal) {
            return null;
        }
        $disk = $this->disk();
        if (! $disk instanceof AwsS3V3Adapter) {
            return null;
        }
        $config = $disk->getConfig();
        $bucket = (string) ($config['bucket'] ?? '');
        if ($bucket === '') {
            return null;
        }
        $key = ltrim(str_replace('\\', '/', $relativePath), '/');
        $root = (string) ($config['root'] ?? '');
        if ($root !== '') {
            $key = trim($root, '/').'/'.$key;
        }

        return [
            'client' => $disk->getClient(),
            'bucket' => $bucket,
            'key' => $key,
        ];
    }

    /**
     * @param  array{client: S3Client, bucket: string, key: string}  $s3
     * @param  array{start: int, end: int}  $range
     * @param  array<string, string>  $headers
     */
    private function streamS3ObjectRange(array $s3, array $range, int $size, array $headers): StreamedResponse
    {
        $start = $range['start'];
        $end = $range['end'];
        $length = $end - $start + 1;

        try {
            $result = $s3['client']->getObject([
                'Bucket' => $s3['bucket'],
                'Key' => $s3['key'],
                'Range' => "bytes={$start}-{$end}",
            ]);
        } catch (\Throwable) {
            abort(502, 'Não foi possível ler o arquivo.');
        }

        return response()->stream(function () use ($result): void {
            $body = $result['Body'];
            while (! $body->eof()) {
                echo $body->read(65536);
            }
        }, 206, $headers + [
            'Content-Length' => (string) $length,
            'Content-Range' => "bytes {$start}-{$end}/{$size}",
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array{client: S3Client, bucket: string, key: string}  $s3
     * @param  array<string, string>  $headers
     */
    private function streamS3ObjectFull(array $s3, int $size, array $headers): StreamedResponse
    {
        try {
            $result = $s3['client']->getObject([
                'Bucket' => $s3['bucket'],
                'Key' => $s3['key'],
            ]);
        } catch (\Throwable) {
            abort(502, 'Não foi possível ler o arquivo.');
        }

        return response()->stream(function () use ($result): void {
            $body = $result['Body'];
            while (! $body->eof()) {
                echo $body->read(65536);
            }
        }, 200, $headers + [
            'Content-Length' => (string) $size,
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array{start: int, end: int}  $range
     * @param  array<string, string>  $headers
     */
    private function streamFlysystemRange(Filesystem $disk, string $relativePath, array $range, int $size, array $headers): StreamedResponse
    {
        $start = $range['start'];
        $end = $range['end'];
        $length = $end - $start + 1;

        return response()->stream(function () use ($disk, $relativePath, $start, $length): void {
            $stream = $disk->readStream($relativePath);
            if (! is_resource($stream)) {
                return;
            }
            fseek($stream, $start);
            $remaining = $length;
            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(65536, $remaining));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
            }
            fclose($stream);
        }, 206, $headers + [
            'Content-Length' => (string) $length,
            'Content-Range' => "bytes {$start}-{$end}/{$size}",
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function streamFlysystemFull(Filesystem $disk, string $relativePath, int $size, array $headers): StreamedResponse
    {
        return response()->stream(function () use ($disk, $relativePath): void {
            $stream = $disk->readStream($relativePath);
            if (! is_resource($stream)) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers + [
            'Content-Length' => (string) $size,
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function streamRemotePdf(string $relativePath, array $headers): StreamedResponse
    {
        $request = request();

        return $this->streamRemotePdfWithRange($request, $relativePath, $headers);
    }

    /**
     * Download ou visualização de arquivo genérico (anexos da aula).
     */
    public function streamFileResponse(Request $request, string $relativePath, string $filename, bool $download = true): StreamedResponse
    {
        $this->disk();
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || ! $this->exists($relativePath)) {
            abort(404);
        }

        $filename = trim($filename) ?: basename($relativePath);
        $filename = preg_replace('/[^\w.\-\x{00C0}-\x{024F}]+/u', '_', $filename) ?: 'anexo';
        $mime = $this->mimeForPath($relativePath);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"';

        if ($this->isLocal) {
            $fullPath = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            if (! is_file($fullPath)) {
                abort(404);
            }

            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition,
                'Cache-Control' => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $this->streamRemotePdf($relativePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function mimeForPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}
