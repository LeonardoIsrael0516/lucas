<?php

namespace App\Services;

use App\Models\MemberLesson;
use App\Models\Product;
use Illuminate\Support\Str;

class LessonTemplateService
{
    public function __construct(
        private readonly StorageService $storage
    ) {}

    /**
     * @param  list<int>  $targetLessonIds
     * @return list<MemberLesson>
     */
    public function applyFromSource(
        Product $produto,
        MemberLesson $source,
        array $targetLessonIds,
        bool $keepTargetTitles = true,
        bool $copyStorage = true
    ): array {
        $source = $source->fresh();
        if ($source->product_id !== $produto->id) {
            abort(404);
        }

        $ids = array_values(array_unique(array_map('intval', $targetLessonIds)));
        $ids = array_filter($ids, fn (int $id) => $id > 0 && $id !== (int) $source->id);
        if ($ids === []) {
            return [];
        }

        $targets = MemberLesson::query()
            ->where('product_id', $produto->id)
            ->whereIn('id', $ids)
            ->get();

        $destDir = 'member-area/'.$produto->id;
        $updated = [];

        foreach ($targets as $target) {
            $data = $this->buildPayloadFromSource($source, $copyStorage, $destDir);
            if ($keepTargetTitles) {
                unset($data['title']);
            }
            $target->update($data);
            $updated[] = $target->fresh();
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadFromSource(MemberLesson $source, bool $copyStorage, string $destDir): array
    {
        $contentUrl = $source->content_url;
        if ($copyStorage && is_string($contentUrl) && $contentUrl !== '') {
            $copied = $this->copyStorageUrl($contentUrl, $destDir);
            if ($copied) {
                $contentUrl = $copied;
            }
        }

        return [
            'title' => $source->title,
            'type' => $source->type,
            'content_url' => $contentUrl,
            'link_title' => $source->link_title,
            'content_files' => $this->copyFileList($source->content_files, $copyStorage, $destDir),
            'attachment_files' => $this->copyFileList($source->attachment_files, $copyStorage, $destDir),
            'content_text' => $source->content_text,
            'resource_links' => $source->resource_links,
            'release_after_days' => $source->release_after_days,
            'release_at_date' => $source->release_at_date,
            'duration_seconds' => $source->duration_seconds,
            'is_free' => $source->is_free,
            'watermark_enabled' => (bool) ($source->watermark_enabled ?? false),
        ];
    }

    /**
     * @param  mixed  $files
     * @return array<int, array<string, mixed>>|null
     */
    private function copyFileList(mixed $files, bool $copyStorage, string $destDir): ?array
    {
        if (! is_array($files) || $files === []) {
            return null;
        }

        $out = [];
        foreach ($files as $it) {
            if (! is_array($it)) {
                continue;
            }
            $url = isset($it['url']) ? (string) $it['url'] : '';
            $name = isset($it['name']) ? (string) $it['name'] : 'Arquivo';
            if ($url === '') {
                continue;
            }
            if ($copyStorage) {
                $copied = $this->copyStorageUrl($url, $destDir);
                if ($copied) {
                    $url = $copied;
                }
            }
            $entry = ['url' => $url, 'name' => $name];
            if (isset($it['library_item_id']) && (int) $it['library_item_id'] > 0) {
                $entry['library_item_id'] = (int) $it['library_item_id'];
            }
            $out[] = $entry;
        }

        return $out !== [] ? $out : null;
    }

    private function copyStorageUrl(string $urlOrPath, string $destDir): ?string
    {
        $srcPath = $this->storagePathFromValue($urlOrPath);
        if (! $srcPath) {
            return null;
        }

        $destPath = $this->copyStorageAsset($srcPath, $destDir);

        return $destPath ? $this->storage->url($destPath) : null;
    }

    private function storagePathFromValue(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        if (str_starts_with($v, 'member-area/')
            || str_starts_with($v, 'member-area-gamification/')
            || str_starts_with($v, 'member-pdf-library/')
            || str_starts_with($v, 'products/')) {
            return $v;
        }

        $needle = '/storage/';
        $pos = strpos($v, $needle);
        if ($pos === false) {
            return $this->storage->pathFromStoredUrl($v);
        }
        $path = substr($v, $pos + strlen($needle));
        $path = ltrim((string) $path, '/');

        return $path !== '' ? $path : null;
    }

    private function copyStorageAsset(string $srcPath, string $destDir): ?string
    {
        if (! $this->storage->exists($srcPath)) {
            return null;
        }

        $ext = pathinfo($srcPath, PATHINFO_EXTENSION);
        $base = pathinfo($srcPath, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $base) ?: 'file';
        $name = $safeBase.'-copy-'.Str::lower(Str::random(6)).($ext ? '.'.$ext : '');
        $destPath = rtrim($destDir, '/').'/'.$name;

        try {
            $ok = $this->storage->disk()->copy($srcPath, $destPath);

            return $ok ? $destPath : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
