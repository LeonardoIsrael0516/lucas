<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesStudentSupportProps;
use App\Models\MemberCertificateIssued;
use App\Models\MemberComment;
use App\Models\MemberCommunityPage;
use App\Models\MemberCommunityPost;
use App\Models\MemberCommunityPostComment;
use App\Models\MemberCommunityPostLike;
use App\Models\MemberActivityLog;
use App\Models\MemberInternalProduct;
use App\Models\MemberLesson;
use App\Models\MemberLessonBookmark;
use App\Models\MemberLessonLike;
use App\Models\MemberLessonPdfAnnotation;
use App\Models\MemberLessonRating;
use App\Models\MemberLessonProgress;
use App\Models\MemberModule;
use App\Models\MemberSection;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\MemberAreaResolver;
use App\Services\MemberCommentService;
use App\Services\MemberProgressService;
use App\Services\StorageService;
use App\Support\StudentAreaBranding;
use App\Support\StudentAreaSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MemberAreaAppController extends Controller
{
    use SharesStudentSupportProps;

    public function __construct(
        protected MemberProgressService $progressService,
        protected MemberAreaResolver $resolver,
        protected GamificationService $gamificationService
    ) {}

    /**
     * Best-effort activity log for proof/compliance. Must never break student UX.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logMemberActivity(Request $request, Product $product, ?User $user, string $event, array $metadata = []): void
    {
        try {
            MemberActivityLog::create([
                'tenant_id' => $product->tenant_id ?? $user?->tenant_id,
                'user_id' => $user?->id,
                'product_id' => $product->id,
                'event' => $event,
                'metadata' => $metadata,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // ignore (best-effort)
        }
    }

    /**
     * Best-effort page count extraction for PDF-like lessons.
     * We only expose it when it already exists in content_files payload.
     *
     * @param  mixed  $contentFiles
     */
    private function lessonPagesCountFromContentFiles(mixed $contentFiles): ?int
    {
        if (! is_array($contentFiles) || $contentFiles === []) {
            return null;
        }

        $sum = 0;
        $found = false;
        foreach ($contentFiles as $it) {
            if (! is_array($it)) {
                continue;
            }

            $raw = $it['pages_count'] ?? $it['page_count'] ?? $it['pages'] ?? $it['total_pages'] ?? null;
            if ($raw === null) {
                continue;
            }

            $n = is_numeric($raw) ? (int) $raw : 0;
            if ($n <= 0) {
                continue;
            }

            $sum += $n;
            $found = true;
        }

        return $found ? $sum : null;
    }

    /**
     * Entrada do curso (/m/{slug}): última aula vista, senão primeira aula desbloqueada, senão lista de módulos.
     */
    private function resolveEntryRedirect(Request $request, Product $product, User $user, string $slug): RedirectResponse
    {
        $routeSlug = $product->checkout_slug ?: $slug;
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();

        $log = MemberActivityLog::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('event', 'member_area.lesson_view')
            ->orderByDesc('created_at')
            ->first();

        if ($log && is_array($log->metadata) && ! empty($log->metadata['lesson_id'])) {
            $lesson = MemberLesson::query()->with('module')->find((int) $log->metadata['lesson_id']);
            if ($lesson && $lesson->module) {
                if ((string) $lesson->product_id !== (string) $product->id) {
                    $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
                    if ($wrapper === null) {
                        // aula de outro produto sem vínculo no curso atual — ignora log
                    } else {
                        $embedRedirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
                        if ($embedRedirect !== null) {
                            return $embedRedirect;
                        }
                        $effectiveModule = $this->resolveContentModuleForWrapper($wrapper);
                        $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
                        $lessonLock = $this->lessonLockPayload($lesson, $effectiveModule, $accessStartAt, $now);
                        if (($moduleLock['is_locked'] ?? false) !== true && ($lessonLock['is_locked'] ?? false) !== true) {
                            return redirect()->route(
                                $this->memberAreaModuleRouteName($request),
                                ['slug' => $routeSlug, 'module' => $wrapper->id, 'aula' => $lesson->id]
                            );
                        }
                    }
                } else {
                    $effectiveModule = $lesson->module->source_member_module_id
                        ? $this->resolveContentModuleForWrapper($lesson->module)
                        : $lesson->module;
                    $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
                    $lessonLock = $this->lessonLockPayload($lesson, $effectiveModule, $accessStartAt, $now);
                    if (($moduleLock['is_locked'] ?? false) !== true && ($lessonLock['is_locked'] ?? false) !== true) {
                        $moduleRouteId = (int) ($log->metadata['module_id'] ?? $lesson->module->id);

                        return redirect()->route(
                            $this->memberAreaModuleRouteName($request),
                            ['slug' => $routeSlug, 'module' => $moduleRouteId, 'aula' => $lesson->id]
                        );
                    }
                }
            }
        }

        $modules = $product->memberModules()->with(['lessons'])->orderBy('position')->get();
        foreach ($modules as $m) {
                if ($m->source_member_module_id) {
                    $embedRedirect = $this->assertEmbeddedProductLinkAccess($m, $user);
                    if ($embedRedirect !== null) {
                        return $embedRedirect;
                    }
                }
                $effective = $m->source_member_module_id ? $this->resolveContentModuleForWrapper($m) : $m;
                if (($this->moduleLockPayload($effective, $accessStartAt, $now)['is_locked'] ?? false) === true) {
                    continue;
                }
                foreach ($effective->lessons->sortBy('position') as $l) {
                    if (($this->lessonLockPayload($l, $effective, $accessStartAt, $now)['is_locked'] ?? false) === true) {
                        continue;
                    }

                    return redirect()->route(
                        $this->memberAreaModuleRouteName($request),
                        ['slug' => $routeSlug, 'module' => $m->id, 'aula' => $l->id]
                    );
                }
        }

        return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $routeSlug])
            ->with('error', 'Este curso ainda não tem conteúdo disponível.');
    }

    public function show(Request $request, string $slug): RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();

        $this->logMemberActivity($request, $product, $user, 'member_area.open', [
            'path' => '/' . ltrim($request->path(), '/'),
        ]);

        return $this->resolveEntryRedirect($request, $product, $user, $slug);
    }

    public function modulos(Request $request, string $slug): Response
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        $modules = $product->memberModules()->with(['lessons'])->orderBy('position')->get();

        return Inertia::render('MemberAreaApp/Modulos', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'modules' => $modules->map(function (MemberModule $m) use ($accessStartAt, $now, $user) {
                $effective = ($m->source_member_module_id)
                    ? $this->resolveContentModuleForWrapper($m)
                    : $m;

                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'thumbnail' => $m->thumbnail,
                    'cover_mode' => $m->cover_mode ?? 'vertical',
                    'show_title_on_cover' => $m->show_title_on_cover ?? true,
                    ...$this->moduleLockPayload($effective, $accessStartAt, $now),
                    'lessons' => $effective->lessons->map(fn (MemberLesson $l) => [
                        'id' => $l->id,
                        'title' => $l->title,
                        'type' => $l->type,
                        'duration_seconds' => $l->duration_seconds,
                        'is_completed' => $this->isLessonCompleted($user->id, $l->id),
                        ...$this->lessonLockPayload($l, $effective, $accessStartAt, $now),
                    ])->values()->all(),
                ];
            })->values()->all(),
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            ...$this->pushProps($product),
            ...$this->studentHubProps($request, $product),
        ] + $this->gamificationProps($product, $user));
    }

    public function moduleContent(Request $request, string $slug, MemberModule $module): Response|RedirectResponse
    {
        $product = $this->getProduct($request);
        if ($module->product_id !== $product->id) {
            abort(404);
        }
        $user = $request->user();
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        if ($module->source_member_module_id) {
            $redirect = $this->assertEmbeddedProductLinkAccess($module, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $module->load('section');
        $effectiveModule = $this->resolveContentModuleForWrapper($module);
        $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
        if (($moduleLock['is_locked'] ?? false) === true) {
            return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $slug])
                ->with('error', $moduleLock['lock_message'] ?? 'Módulo ainda não liberado.');
        }
        $lessons = $effectiveModule->lessons->map(function (MemberLesson $l) use ($user, $effectiveModule, $accessStartAt, $now) {
            $payload = [
                'id' => $l->id,
                'title' => $l->title,
                'type' => $l->type,
                'position' => $l->position,
                'duration_seconds' => $l->duration_seconds,
                'is_completed' => $this->isLessonCompleted($user->id, $l->id),
                ...$this->lessonLockPayload($l, $effectiveModule, $accessStartAt, $now),
            ];

            if (in_array($l->type, [MemberLesson::TYPE_PDF, MemberLesson::TYPE_PDF_PRESENTATION, MemberLesson::TYPE_PDF_READER], true)) {
                $pages = $this->lessonPagesCountFromContentFiles($l->content_files);
                if ($pages !== null) {
                    $payload['pages_count'] = $pages;
                }
            }

            return $payload;
        })->values()->all();

        $lessonId = $request->query('aula');
        $currentLesson = $lessonId
            ? $effectiveModule->lessons->firstWhere('id', (int) $lessonId)
            : $effectiveModule->lessons->first();
        if ($currentLesson) {
            $lock = $this->lessonLockPayload($currentLesson, $effectiveModule, $accessStartAt, $now);
            if (($lock['is_locked'] ?? false) === true) {
                $firstUnlocked = $effectiveModule->lessons->first(function (MemberLesson $l) use ($effectiveModule, $accessStartAt, $now) {
                    return ($this->lessonLockPayload($l, $effectiveModule, $accessStartAt, $now)['is_locked'] ?? false) !== true;
                });
                if ($firstUnlocked) {
                    return redirect()->route($this->memberAreaModuleRouteName($request), ['slug' => $slug, 'module' => $module->id, 'aula' => $firstUnlocked->id])
                        ->with('error', $lock['lock_message'] ?? 'Aula ainda não liberada.');
                }
                $request->session()->flash('error', $lock['lock_message'] ?? 'Aulas ainda não liberadas.');
                $currentLesson = null;
            }
        }

        $currentLessonData = null;
        if ($currentLesson) {
            $this->progressService->ensureLessonStarted($currentLesson, $user);
            $this->logMemberActivity($request, $product, $user, 'member_area.lesson_view', [
                'lesson_id' => $currentLesson->id,
                'lesson_product_id' => $currentLesson->product_id,
                'module_id' => $module->id,
                'embedded' => (bool) $module->source_member_module_id,
            ]);
            $currentLessonData = [
                'id' => $currentLesson->id,
                'title' => $currentLesson->title,
                'type' => $currentLesson->type,
                'content_url' => $currentLesson->content_url,
                'content_files' => $currentLesson->content_files,
                'attachment_files' => $currentLesson->attachment_files,
                'link_title' => $currentLesson->link_title,
                'content_text' => \App\Support\HtmlSanitizer::sanitize($currentLesson->content_text),
                'resource_links' => $this->sanitizeLessonResourceLinksForResponse($currentLesson->resource_links),
                'duration_seconds' => $currentLesson->duration_seconds,
                'is_completed' => $this->isLessonCompleted($user->id, $currentLesson->id),
                'module' => ['id' => $module->id, 'title' => $module->title],
                'section' => $module->section ? ['id' => $module->section->id, 'title' => $module->section->title] : null,
                'watermark_enabled' => (bool) ($currentLesson->watermark_enabled ?? false),
            ];
            if ($currentLessonData['watermark_enabled']) {
                $currentLessonData['student'] = $this->getStudentWatermarkData($user, $product);
            }
            if ($this->isPdfLessonType($currentLesson->type)) {
                $currentLessonData = array_merge($currentLessonData, $this->lessonEngagementExtras($currentLesson, $user));
            }
        }

        $progressPercent = $this->progressService->completionPercent($product, $user);

        $modulesPayload = $product->memberModules()
            ->with(['lessons' => fn ($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get()
            ->map(function (MemberModule $m) use ($user, $accessStartAt, $now) {
                $coverMode = $m->cover_mode ?? 'vertical';
                $lessonsPayload = $m->lessons->map(function (MemberLesson $l) use ($user, $m, $accessStartAt, $now) {
                    $payload = [
                        'id' => $l->id,
                        'title' => $l->title,
                        'type' => $l->type,
                        'is_completed' => $this->isLessonCompleted($user->id, $l->id),
                        ...$this->lessonLockPayload($l, $m, $accessStartAt, $now),
                    ];
                    if (in_array($l->type, [MemberLesson::TYPE_PDF, MemberLesson::TYPE_PDF_PRESENTATION, MemberLesson::TYPE_PDF_READER], true)) {
                        $pages = $this->lessonPagesCountFromContentFiles($l->content_files);
                        if ($pages !== null) {
                            $payload['pages_count'] = $pages;
                        }
                    }

                    return $payload;
                })->values()->all();

                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'thumbnail' => $m->thumbnail,
                    'show_title_on_cover' => $m->show_title_on_cover ?? true,
                    'cover_mode' => $coverMode,
                    'lessons' => $lessonsPayload,
                    ...$this->moduleLockPayload($m, $accessStartAt, $now),
                ];
            })->values()->all();

        $config = $product->member_area_config;
        $commentsEnabled = (bool) ($config['comments_enabled'] ?? false);
        $commentsRequireApproval = (bool) ($config['comments_require_approval'] ?? true);
        $lessonComments = [];
        if ($commentsEnabled && $currentLesson) {
            $lessonComments = MemberComment::forProduct($product->id)
                ->where('member_lesson_id', $currentLesson->id)
                ->status(MemberComment::STATUS_APPROVED)
                ->with('user:id,name,avatar')
                ->latest()
                ->get()
                ->map(fn (MemberComment $c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'user' => $c->user ? [
                        'id' => $c->user->id,
                        'name' => $c->user->name,
                        'avatar_url' => $c->user->avatar ? (new StorageService($product->tenant_id))->url($c->user->avatar) : null,
                    ] : null,
                    'created_at' => $c->created_at->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('MemberAreaApp/ModuleContent', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'thumbnail' => $module->thumbnail,
                'cover_mode' => $module->cover_mode ?? ($module->section?->cover_mode ?? 'vertical'),
            ],
            'lessons' => $lessons,
            'current_lesson' => $currentLessonData,
            'progress_percent' => $progressPercent,
            'course_lesson_progress' => [
                'completed' => $this->progressService->completedLessonsCount($product, $user),
                'total' => $this->progressService->totalLessonsCount($product),
            ],
            'modules' => $modulesPayload,
            'comments_enabled' => $commentsEnabled,
            'comments_require_approval' => $commentsRequireApproval,
            'lesson_comments' => $lessonComments,
            ...$this->pushProps($product),
            ...$this->studentHubProps($request, $product),
        ]);
    }

    public function lesson(Request $request, string $slug, MemberLesson $lesson): RedirectResponse
    {
        $product = $this->getProduct($request);
        $routeSlug = $product->checkout_slug ?: $slug;
        $user = $request->user();
        $lesson->load('module');
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        $effectiveModule = $wrapper !== null
            ? $this->resolveContentModuleForWrapper($wrapper)
            : $lesson->module;
        if ($effectiveModule) {
            $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
            if (($moduleLock['is_locked'] ?? false) === true) {
                return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $routeSlug])
                    ->with('error', $moduleLock['lock_message'] ?? 'Módulo ainda não liberado.');
            }
        }
        $lessonLock = $this->lessonLockPayload($lesson, $effectiveModule, $accessStartAt, $now);
        if (($lessonLock['is_locked'] ?? false) === true) {
            $moduleRouteId = $wrapper?->id ?? $lesson->module?->id;
            if ($moduleRouteId) {
                return redirect()->route($this->memberAreaModuleRouteName($request), ['slug' => $routeSlug, 'module' => $moduleRouteId])
                    ->with('error', $lessonLock['lock_message'] ?? 'Aula ainda não liberada.');
            }
            return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $routeSlug])
                ->with('error', $lessonLock['lock_message'] ?? 'Aula ainda não liberada.');
        }
        $this->progressService->ensureLessonStarted($lesson, $user);

        $moduleRouteId = $wrapper?->id ?? $lesson->module?->id;
        $this->logMemberActivity($request, $product, $user, 'member_area.lesson_view', [
            'lesson_id' => $lesson->id,
            'lesson_product_id' => $lesson->product_id,
            'module_id' => $moduleRouteId,
            'embedded' => $wrapper !== null,
        ]);

        if ($moduleRouteId) {
            return redirect()->route(
                $this->memberAreaModuleRouteName($request),
                ['slug' => $routeSlug, 'module' => $moduleRouteId, 'aula' => $lesson->id]
            );
        }

        return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $routeSlug]);
    }

    /**
     * Proxy same-origin para PDF (leitor/apresentação): pdf.js com credenciais + Range.
     * Arquivos no R2/S3 são lidos pelo servidor (stream), sem depender de CORS no browser.
     * ?download=1 força Content-Disposition attachment (botão Baixar material).
     */
    public function presentationPdf(Request $request, string $slug, MemberLesson $lesson, int $fileIndex): SymfonyResponse
    {
        $this->assertLessonViewableForPdf($request, $lesson);
        if (! in_array($lesson->type, [MemberLesson::TYPE_PDF_PRESENTATION, MemberLesson::TYPE_PDF_READER], true)) {
            abort(404);
        }
        $urls = $this->pdfPresentationSourceUrls($lesson);
        if ($fileIndex < 0 || $fileIndex >= count($urls)) {
            abort(404);
        }
        $source = $urls[$fileIndex];
        $filename = $this->pdfLessonFileName($lesson, $fileIndex);
        $download = $request->boolean('download');

        $this->progressService->ensureLessonStarted($lesson, $request->user());

        $product = $this->getProduct($request);
        $tenantId = (int) ($product->tenant_id ?? $request->user()?->tenant_id ?? 0);
        $storage = new StorageService($tenantId > 0 ? $tenantId : null);

        $path = $storage->pathFromStoredUrl($source);
        if ($path !== null && $storage->exists($path)) {
            return $storage->streamPdfResponse($request, $path, $filename, $download);
        }

        if (! preg_match('#^https?://#i', $source)) {
            abort(404);
        }

        $remote = Http::timeout(120)->connectTimeout(30)->get($source);
        if (! $remote->successful()) {
            abort(502, 'Não foi possível obter o arquivo.');
        }

        $pathPart = parse_url($source, PHP_URL_PATH);
        $fallbackName = $pathPart ? basename($pathPart) : 'documento.pdf';
        if ($fallbackName === '' || $fallbackName === '/') {
            $fallbackName = 'documento.pdf';
        }
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$fallbackName.'"';

        return response($remote->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Proxy para anexos extras da introdução da aula (?download=1).
     */
    public function lessonAttachment(Request $request, string $slug, MemberLesson $lesson, int $fileIndex): SymfonyResponse
    {
        $this->assertLessonViewableForPdf($request, $lesson);
        $files = is_array($lesson->attachment_files) ? $lesson->attachment_files : [];
        if ($fileIndex < 0 || $fileIndex >= count($files)) {
            abort(404);
        }
        $item = $files[$fileIndex];
        if (! is_array($item)) {
            abort(404);
        }
        $source = (string) ($item['url'] ?? '');
        if ($source === '') {
            abort(404);
        }
        $filename = (string) ($item['name'] ?? 'anexo');
        $download = $request->boolean('download');

        $product = $this->getProduct($request);
        $tenantId = (int) ($product->tenant_id ?? $request->user()?->tenant_id ?? 0);
        $storage = new StorageService($tenantId > 0 ? $tenantId : null);

        $path = $storage->pathFromStoredUrl($source);
        if ($path !== null && $storage->exists($path)) {
            return $storage->streamFileResponse($request, $path, $filename, $download);
        }

        if (! preg_match('#^https?://#i', $source)) {
            abort(404);
        }

        $remote = Http::timeout(120)->connectTimeout(30)->get($source);
        if (! $remote->successful()) {
            abort(502, 'Não foi possível obter o arquivo.');
        }

        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"';

        return response($remote->body(), 200, [
            'Content-Type' => $remote->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET — marcações do usuário no leitor PDF (por arquivo).
     */
    public function getLessonPdfAnnotations(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        if ($lesson->type !== MemberLesson::TYPE_PDF_READER) {
            abort(404);
        }

        $rows = MemberLessonPdfAnnotation::query()
            ->where('user_id', $user->id)
            ->where('member_lesson_id', $lesson->id)
            ->get(['file_index', 'payload']);

        $byFile = [];
        foreach ($rows as $row) {
            $byFile[(string) $row->file_index] = is_array($row->payload) ? $row->payload : [];
        }

        return response()->json(['annotations_by_file' => $byFile]);
    }

    /**
     * PUT — salva marcações de um arquivo PDF (lista de highlights).
     *
     * @param  array<string, mixed>  $payload
     */
    public function putLessonPdfAnnotations(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        if ($lesson->type !== MemberLesson::TYPE_PDF_READER) {
            abort(404);
        }

        $validated = $request->validate([
            'file_index' => ['required', 'integer', 'min:0'],
            'highlights' => ['required', 'array', 'max:500'],
            'highlights.*.id' => ['required', 'string', 'max:64'],
            'highlights.*.page' => ['required', 'integer', 'min:1'],
            'highlights.*.color' => ['required', 'string', 'in:yellow,green,pink'],
            'highlights.*.x' => ['required', 'numeric', 'min:0', 'max:1'],
            'highlights.*.y' => ['required', 'numeric', 'min:0', 'max:1'],
            'highlights.*.width' => ['required', 'numeric', 'min:0', 'max:1'],
            'highlights.*.height' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        MemberLessonPdfAnnotation::updateOrCreate(
            [
                'user_id' => $user->id,
                'member_lesson_id' => $lesson->id,
                'file_index' => $validated['file_index'],
            ],
            [
                'payload' => $validated['highlights'],
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * POST — alterna curtida na aula (somente tipo pdf_reader).
     */
    public function toggleLessonLike(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        if ($lesson->type !== MemberLesson::TYPE_PDF_READER) {
            abort(404);
        }

        $liked = false;
        $count = (int) ($lesson->likes_count ?? 0);

        DB::transaction(function () use ($lesson, $user, &$liked, &$count): void {
            $lessonRow = MemberLesson::query()->whereKey($lesson->id)->lockForUpdate()->first();
            if (! $lessonRow) {
                return;
            }
            $existing = MemberLessonLike::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->first();
            if ($existing) {
                $existing->delete();
                $lessonRow->decrement('likes_count');
                $liked = false;
            } else {
                MemberLessonLike::create([
                    'user_id' => $user->id,
                    'member_lesson_id' => $lesson->id,
                ]);
                $lessonRow->increment('likes_count');
                $liked = true;
            }
            $count = (int) $lessonRow->fresh()->likes_count;
        });

        return response()->json([
            'liked' => $liked,
            'likes_count' => $count,
        ]);
    }

    /**
     * PUT — avaliação 1–5 estrelas (aulas PDF).
     */
    public function updateLessonRating(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        $this->assertPdfLessonEngagementType($lesson);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $newRating = (int) $validated['rating'];
        $payload = [];

        DB::transaction(function () use ($lesson, $user, $newRating, &$payload): void {
            $lessonRow = MemberLesson::query()->whereKey($lesson->id)->lockForUpdate()->first();
            if (! $lessonRow) {
                return;
            }

            $existing = MemberLessonRating::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->first();

            $oldRating = $existing ? (int) $existing->rating : 0;
            if ($oldRating === $newRating) {
                $payload = $this->lessonRatingResponsePayload($lessonRow, $user, $newRating);

                return;
            }

            if ($existing) {
                $existing->update(['rating' => $newRating]);
                $lessonRow->ratings_sum = max(0, (int) $lessonRow->ratings_sum - $oldRating + $newRating);
            } else {
                MemberLessonRating::create([
                    'user_id' => $user->id,
                    'member_lesson_id' => $lesson->id,
                    'rating' => $newRating,
                ]);
                $lessonRow->ratings_count = (int) $lessonRow->ratings_count + 1;
                $lessonRow->ratings_sum = (int) $lessonRow->ratings_sum + $newRating;
            }

            $lessonRow->save();
            $payload = $this->lessonRatingResponsePayload($lessonRow->fresh(), $user, $newRating);
        });

        return response()->json($payload);
    }

    /**
     * POST — alterna favorito/salvar aula (aulas PDF).
     */
    public function toggleLessonBookmark(Request $request, string $slug, MemberLesson $lesson): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }
        $this->assertLessonViewableForPdf($request, $lesson);
        $this->assertPdfLessonEngagementType($lesson);

        $bookmarked = false;

        DB::transaction(function () use ($lesson, $user, &$bookmarked): void {
            $existing = MemberLessonBookmark::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $bookmarked = false;
            } else {
                MemberLessonBookmark::create([
                    'user_id' => $user->id,
                    'member_lesson_id' => $lesson->id,
                ]);
                $bookmarked = true;
            }
        });

        return response()->json(['user_bookmarked' => $bookmarked]);
    }

    /**
     * @return array{likes_count: int, user_liked: bool}
     */
    private function pdfReaderLessonExtras(MemberLesson $lesson, User $user): array
    {
        return [
            'likes_count' => (int) ($lesson->likes_count ?? 0),
            'user_liked' => MemberLessonLike::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->exists(),
        ];
    }

    /**
     * @return array{
     *     user_rating: int|null,
     *     user_bookmarked: bool,
     *     ratings_avg: float|null,
     *     ratings_count: int,
     *     likes_count?: int,
     *     user_liked?: bool
     * }
     */
    private function lessonEngagementExtras(MemberLesson $lesson, User $user): array
    {
        $count = (int) ($lesson->ratings_count ?? 0);
        $sum = (int) ($lesson->ratings_sum ?? 0);
        $userRating = MemberLessonRating::query()
            ->where('user_id', $user->id)
            ->where('member_lesson_id', $lesson->id)
            ->value('rating');

        $extras = [
            'user_rating' => $userRating !== null ? (int) $userRating : null,
            'user_bookmarked' => MemberLessonBookmark::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->exists(),
            'ratings_avg' => $count > 0 ? round($sum / $count, 1) : null,
            'ratings_count' => $count,
        ];

        if ($lesson->type === MemberLesson::TYPE_PDF_READER) {
            $extras = array_merge($extras, $this->pdfReaderLessonExtras($lesson, $user));
        }

        return $extras;
    }

    /**
     * @return array{user_rating: int, user_bookmarked: bool, ratings_avg: float|null, ratings_count: int}
     */
    private function lessonRatingResponsePayload(MemberLesson $lesson, User $user, int $userRating): array
    {
        $count = (int) ($lesson->ratings_count ?? 0);
        $sum = (int) ($lesson->ratings_sum ?? 0);

        return [
            'user_rating' => $userRating,
            'user_bookmarked' => MemberLessonBookmark::query()
                ->where('user_id', $user->id)
                ->where('member_lesson_id', $lesson->id)
                ->exists(),
            'ratings_avg' => $count > 0 ? round($sum / $count, 1) : null,
            'ratings_count' => $count,
        ];
    }

    private function isPdfLessonType(?string $type): bool
    {
        return in_array($type, [
            MemberLesson::TYPE_PDF,
            MemberLesson::TYPE_PDF_PRESENTATION,
            MemberLesson::TYPE_PDF_READER,
        ], true);
    }

    private function assertPdfLessonEngagementType(MemberLesson $lesson): void
    {
        if (! $this->isPdfLessonType($lesson->type)) {
            abort(404);
        }
    }

    public function completeLesson(Request $request, string $slug, MemberLesson $lesson): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado.'], 401);
        }
        $product = $this->getProduct($request);
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $this->progressService->markLessonCompleted($lesson->id, $user);

        $this->logMemberActivity($request, $product, $user, 'member_area.lesson_complete', [
            'lesson_id' => $lesson->id,
            'lesson_product_id' => $lesson->product_id,
            'embedded' => $wrapper !== null,
        ]);

        $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $user);
        if ($newlyUnlocked !== []) {
            $request->session()->flash('newly_unlocked_achievements', $newlyUnlocked);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }
        $percent = $this->progressService->completionPercent($product, $user);

        return response()->json(['success' => true, 'progress_percent' => $percent, 'newly_unlocked_achievements' => $newlyUnlocked]);
    }

    public function storeLessonComment(Request $request, string $slug, MemberLesson $lesson): JsonResponse|RedirectResponse
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }
        $config = $product->member_area_config;
        if (empty($config['comments_enabled'])) {
            abort(403, 'Comentários desativados para este produto.');
        }
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);
        $requireApproval = ! empty($config['comments_require_approval']);
        $initialStatus = $requireApproval ? MemberComment::STATUS_PENDING : MemberComment::STATUS_APPROVED;
        $commentService = app(MemberCommentService::class);
        $commentService->create(
            $product,
            $request->user(),
            $validated['content'],
            $lesson->id,
            null,
            $initialStatus
        );
        $message = $requireApproval ? 'Comentário enviado e aguardando aprovação.' : 'Comentário publicado.';
        if (! $requireApproval) {
            $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $request->user());
            if ($newlyUnlocked !== []) {
                $request->session()->flash('newly_unlocked_achievements', $newlyUnlocked);
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function loja(Request $request, string $slug): Response
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $internalProducts = $product->memberInternalProducts()->with('relatedProduct')->orderBy('position')->get();
        $userProductIds = $user->products()->pluck('products.id')->flip()->all();

        $items = $internalProducts->map(fn (MemberInternalProduct $ip) => [
            'id' => $ip->related_product_id,
            'name' => $ip->relatedProduct?->name,
            'description' => $ip->relatedProduct?->description,
            'image_url' => $ip->relatedProduct?->image ? (new StorageService($product->tenant_id))->url($ip->relatedProduct->image) : null,
            'checkout_slug' => $ip->relatedProduct?->checkout_slug,
            'price' => $ip->relatedProduct?->price,
            'has_access' => isset($userProductIds[$ip->related_product_id]),
        ])->values()->all();

        return Inertia::render('MemberAreaApp/Loja', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'items' => $items,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            ...$this->pushProps($product),
            ...$this->studentHubProps($request, $product),
        ] + $this->gamificationProps($product, $user));
    }

    public function comunidade(Request $request, string $slug): Response|\Illuminate\Http\RedirectResponse
    {
        $product = $this->getProduct($request);
        $tenantId = $product->tenant_id ? (int) $product->tenant_id : null;
        if (\App\Support\StudentAreaSettings::communityEnabled($tenantId)) {
            return redirect()->route('student-hub.comunidade');
        }
        $user = $request->user();
        $pages = $product->memberCommunityPages()->orderBy('position')->get();
        $defaultPage = $pages->firstWhere('is_default', true);
        if ($defaultPage) {
            $routeName = $request->route()->getName();
            $pageRouteName = str_ends_with($routeName, '.host') ? 'member-area-app.comunidade.page.host' : 'member-area-app.comunidade.page';

            return redirect()->route($pageRouteName, ['slug' => $slug, 'pageSlug' => $defaultPage->slug]);
        }

        return Inertia::render('MemberAreaApp/Comunidade', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'pages' => $pages->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'icon' => $p->icon,
                'slug' => $p->slug,
                'banner_url' => $p->banner ? (new StorageService($product->tenant_id))->url($p->banner) : null,
            ])->values()->all(),
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            ...$this->pushProps($product),
            ...$this->studentHubProps($request, $product),
        ] + $this->gamificationProps($product, $user));
    }

    public function comunidadePage(Request $request, string $slug, string $pageSlug): Response
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        $config = $product->member_area_config;
        $pages = $product->memberCommunityPages()->orderBy('position')->get();
        $page = $pages->firstWhere('slug', $pageSlug);
        if (! $page) {
            abort(404);
        }
        $postsQuery = $page->posts()->with(['user:id,name,email,avatar', 'likes', 'comments.user:id,name,avatar'])->latest();
        $posts = $postsQuery->paginate(20);
        $canDeleteAny = $user->canAccessPanel() && $user->tenant_id === $product->tenant_id;
        $usersCanDeleteOwn = (bool) ($config['community_users_can_delete_own_posts'] ?? true);
        $posts->getCollection()->transform(function (MemberCommunityPost $post) use ($user) {
            $comments = $post->comments->map(fn (MemberCommunityPostComment $c) => [
                'id' => $c->id,
                'content' => $c->content,
                'created_at' => $c->created_at->format('d/m/Y H:i'),
                'user' => $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'avatar_url' => $c->user->avatar ? (new StorageService($product->tenant_id))->url($c->user->avatar) : null,
                ] : null,
            ])->values()->all();

            return array_merge($post->toArray(), [
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'avatar_url' => $post->user->avatar ? (new StorageService($product->tenant_id))->url($post->user->avatar) : null,
                ] : null,
                'likes_count' => $post->likes->count(),
                'user_has_liked' => $post->likes->contains('user_id', $user->id),
                'comments' => $comments,
            ]);
        });

        return Inertia::render('MemberAreaApp/ComunidadePage', [
            'product' => $this->productToArray($product),
            'config' => $config,
            'auth_user_id' => $user->id,
            'can_delete_any_post' => $canDeleteAny,
            'community_users_can_delete_own_posts' => $usersCanDeleteOwn,
            'pages' => $pages->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'icon' => $p->icon,
                'slug' => $p->slug,
                'banner_url' => $p->banner ? (new StorageService($product->tenant_id))->url($p->banner) : null,
            ])->values()->all(),
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'icon' => $page->icon,
                'slug' => $page->slug,
                'banner_url' => $page->banner ? (new StorageService($product->tenant_id))->url($page->banner) : null,
                'is_public_posting' => $page->is_public_posting,
            ],
            'posts' => $posts,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            ...$this->pushProps($product),
            ...$this->studentHubProps($request, $product),
        ] + $this->gamificationProps($product, $user));
    }

    public function storeCommunityPost(Request $request, string $slug, string $pageSlug): RedirectResponse
    {
        $product = $this->getProduct($request);
        $page = MemberCommunityPage::where('product_id', $product->id)->where('slug', $pageSlug)->firstOrFail();
        if (! $page->is_public_posting) {
            abort(403, 'Apenas o instrutor pode postar nesta página.');
        }
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);
        $imagePath = null;
        if ($request->hasFile('image')) {
            $storage = new StorageService($product->tenant_id);
            $imagePath = $storage->putFile('member-area-posts/'.$product->id, $request->file('image'));
        }
        MemberCommunityPost::create([
            'member_community_page_id' => $page->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'image' => $imagePath,
        ]);

        return back()->with('success', 'Post publicado.');
    }

    public function destroyCommunityPost(Request $request, string $slug, string $pageSlug, MemberCommunityPost $post): RedirectResponse
    {
        $product = $this->getProduct($request);
        $page = MemberCommunityPage::where('product_id', $product->id)->where('slug', $pageSlug)->firstOrFail();
        if ($post->member_community_page_id !== $page->id) {
            abort(404);
        }
        $user = $request->user();
        $config = $product->member_area_config;
        $canDeleteAny = $user->canAccessPanel() && $user->tenant_id === $product->tenant_id;
        $usersCanDeleteOwn = (bool) ($config['community_users_can_delete_own_posts'] ?? true);
        $isAuthor = $post->user_id === $user->id;
        if (! $canDeleteAny && ! ($isAuthor && $usersCanDeleteOwn)) {
            abort(403, 'Você não pode excluir esta postagem.');
        }
        $post->delete();

        return back()->with('success', 'Postagem excluída.');
    }

    public function likeCommunityPost(Request $request, string $slug, string $pageSlug, MemberCommunityPost $post): JsonResponse
    {
        $product = $this->getProduct($request);
        $page = MemberCommunityPage::where('product_id', $product->id)->where('slug', $pageSlug)->firstOrFail();
        if ($post->member_community_page_id !== $page->id) {
            abort(404);
        }
        MemberCommunityPostLike::firstOrCreate(
            ['member_community_post_id' => $post->id, 'user_id' => $request->user()->id]
        );

        return response()->json([
            'likes_count' => $post->likes()->count(),
            'user_has_liked' => true,
        ]);
    }

    public function unlikeCommunityPost(Request $request, string $slug, string $pageSlug, MemberCommunityPost $post): JsonResponse
    {
        $product = $this->getProduct($request);
        $page = MemberCommunityPage::where('product_id', $product->id)->where('slug', $pageSlug)->firstOrFail();
        if ($post->member_community_page_id !== $page->id) {
            abort(404);
        }
        MemberCommunityPostLike::where('member_community_post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'likes_count' => $post->likes()->count(),
            'user_has_liked' => false,
        ]);
    }

    public function storeCommunityPostComment(Request $request, string $slug, string $pageSlug, MemberCommunityPost $post): JsonResponse|RedirectResponse
    {
        $product = $this->getProduct($request);
        $page = MemberCommunityPage::where('product_id', $product->id)->where('slug', $pageSlug)->firstOrFail();
        if ($post->member_community_page_id !== $page->id) {
            abort(404);
        }
        $validated = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $comment = MemberCommunityPostComment::create([
            'member_community_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);
        $comment->load('user:id,name,avatar');
        if ($request->expectsJson()) {
            return response()->json([
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at->format('d/m/Y H:i'),
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'avatar_url' => $comment->user->avatar ? (new StorageService($product->tenant_id))->url($comment->user->avatar) : null,
                    ] : null,
                ],
            ]);
        }

        return back()->with('success', 'Comentário adicionado.');
    }

    public function certificado(Request $request, string $slug): Response|RedirectResponse
    {
        $product = $this->getProduct($request);
        $tenantId = $product->tenant_id ? (int) $product->tenant_id : null;
        if (\App\Support\StudentAreaSettings::certificateEnabled($tenantId)) {
            return redirect()->route('student-hub.certificados');
        }
        $user = $request->user();
        $certConfig = \App\Support\StudentAreaSettings::certificateConfig($tenantId);

        if (empty($certConfig['enabled'])) {
            return redirect()->route($this->memberAreaModulosRouteName($request), ['slug' => $product->checkout_slug ?: $slug])
                ->with('error', 'O certificado não está habilitado para este curso.');
        }

        $progressPercent = $this->progressService->completionPercent($product, $user);
        $requiredPercent = \App\Support\StudentAreaSettings::certificateCompletionPercentForProduct($product);
        $issued = MemberCertificateIssued::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($progressPercent >= $requiredPercent && ! $issued) {
            $issued = $this->progressService->issueCertificate($product, $user);
        }

        $newlyUnlocked = [];
        if ($issued !== null) {
            $newlyUnlocked = $this->gamificationService->checkAndUnlock($product, $user);
        }

        $certificateAvailable = $issued !== null;
        $certTitle = ! empty($certConfig['title']) ? $certConfig['title'] : $product->name;

        $certificatePayload = [
            'title' => $certTitle,
            'issued_at' => $issued ? $issued->issued_at->format('d/m/Y') : null,
            'issued_at_full' => $issued ? $issued->issued_at->format('d/m/Y H:i') : null,
            'completion_percent' => $issued ? $issued->completion_percent : $progressPercent,
            'signature_text' => $certConfig['signature_text'] ?? '',
            'duration_text' => $certConfig['duration_text'] ?? '',
            'font_family' => $certConfig['font_family'] ?? 'sans-serif',
            'platform_name' => ! empty($certConfig['platform_name']) ? $certConfig['platform_name'] : config('app.name'),
            'primary_color' => $certConfig['primary_color'] ?? null,
            'background_image_url' => $certConfig['background_image_url'] ?? null,
            'background_overlay_enabled' => (bool) ($certConfig['background_overlay_enabled'] ?? false),
            'background_overlay_color' => $certConfig['background_overlay_color'] ?? '#000000',
            'background_overlay_opacity' => isset($certConfig['background_overlay_opacity']) ? (float) $certConfig['background_overlay_opacity'] : 50,
            'text_color' => $certConfig['text_color'] ?? null,
            'title_color' => $certConfig['title_color'] ?? null,
            'signature_font_family' => $certConfig['signature_font_family'] ?? 'Dancing Script',
            'print_format' => $certConfig['print_format'] ?? 'A4',
        ];

        return Inertia::render('MemberAreaApp/Certificado', [
            'product' => $this->productToArray($product),
            'config' => $product->member_area_config,
            'recipient_name' => $user->name,
            'certificate_available' => $certificateAvailable,
            'progress_percent' => $progressPercent,
            'completion_required_percent' => $requiredPercent,
            'certificate' => $certificatePayload,
            'base_url' => $this->baseUrlForRequest($product, $request),
            'slug' => $slug,
            'newly_unlocked_achievements' => $newlyUnlocked,
            ...$this->pushProps($product),
            ...$this->studentHubProps($request, $product),
        ] + $this->gamificationProps($product, $user));
    }

    public function pushSubscribe(Request $request, string $slug): JsonResponse
    {
        $product = $this->getProduct($request);
        $config = $product->member_area_config;
        $pwa = $config['pwa'] ?? [];
        if (! ((bool) ($pwa['push_enabled'] ?? false))) {
            return response()->json(['message' => 'Notificações push não estão habilitadas para esta área.'], 403);
        }
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys' => ['required', 'array'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
        ]);
        $keys = $validated['keys'];
        $keys['auth'] = $this->normalizeBase64KeyForPush((string) ($keys['auth'] ?? ''));
        $keys['p256dh'] = $this->normalizeBase64KeyForPush((string) ($keys['p256dh'] ?? ''));
        $subscription = \App\Models\MemberPushSubscription::updateOrCreate(
            [
                'endpoint' => $validated['endpoint'],
            ],
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'keys' => $keys,
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json([
            'success' => true,
            'subscribed' => true,
            'subscription_id' => $subscription->id,
            'updated_at' => $subscription->updated_at?->toISOString(),
        ]);
    }

    private function normalizeBase64KeyForPush(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return $key;
        }
        if (str_contains($key, '+') || str_contains($key, '/')) {
            return strtr($key, ['+' => '-', '/' => '_']);
        }

        return $key;
    }

    private function getProduct(Request $request): Product
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product) {
            abort(404);
        }

        return $product;
    }

    /** @return array{push_enabled: bool, vapid_public: string|null} */
    private function pushProps(Product $product): array
    {
        $config = $product->member_area_config;
        $pwa = $config['pwa'] ?? [];
        $pushEnabled = (bool) ($pwa['push_enabled'] ?? false);

        return [
            'push_enabled' => $pushEnabled,
            'vapid_public' => $pushEnabled ? ($pwa['vapid_public'] ?? null) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentHubProps(Request $request, Product $product): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        $tenantId = $user->tenant_id
            ? (int) $user->tenant_id
            : ($product->tenant_id ? (int) $product->tenant_id : null);

        $hubNav = [
            'community_enabled' => StudentAreaSettings::communityEnabled($tenantId),
            'certificate_enabled' => StudentAreaSettings::certificateEnabled($tenantId),
            'gamification_enabled' => StudentAreaSettings::gamificationEnabled($tenantId),
        ];

        return array_merge([
            'auth_user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $this->studentHubInitials($user->name ?? ''),
            ],
            'notifications_unread_count' => $this->safeUnreadNotificationsCount($user),
            'hub_nav' => $hubNav,
            'profile_href' => route('profile.index'),
            'student_branding' => StudentAreaBranding::forUser($user),
        ], $this->studentSupportPayload($tenantId));
    }

    private function studentHubInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
        }
        if ($parts !== []) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return '?';
    }

    private function safeUnreadNotificationsCount($user): int
    {
        try {
            if (! Schema::hasTable('notifications')) {
                return 0;
            }
            if (! method_exists($user, 'unreadNotifications')) {
                return 0;
            }

            return (int) $user->unreadNotifications()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{gamification_achievements: array} */
    private function gamificationProps(Product $product, User $user): array
    {
        $gamification = $this->gamificationService->gamificationConfigForProduct($product);
        if (empty($gamification['enabled'])) {
            return ['gamification_achievements' => []];
        }

        return [
            'gamification_achievements' => $this->gamificationService->getAchievementsForUser($product, $user),
        ];
    }

    private function productToArray(Product $product): array
    {
        $config = $product->member_area_config;
        $logos = $config['logos'] ?? [];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'checkout_slug' => $product->checkout_slug,
            'logo_light' => $logos['logo_light'] ?? '',
            'logo_dark' => $logos['logo_dark'] ?? '',
        ];
    }

    /**
     * Retorna lista de "continuar assistindo": um item por seção (o último progresso de cada seção).
     */
    private function getContinueWatching(Product $product, $user): array
    {
        $lessonIds = $this->progressService->lessonIdsForMemberAreaHost($product);
        if ($lessonIds === []) {
            return [];
        }

        $progresses = MemberLessonProgress::query()
            ->forUser($user->id)
            ->whereNull('completed_at')
            ->whereIn('member_lesson_id', $lessonIds)
            ->with('lesson.module')
            ->latest('updated_at')
            ->get();

        $wrappers = MemberModule::query()
            ->where('product_id', $product->id)
            ->whereNotNull('source_member_module_id')
            ->get()
            ->keyBy('source_member_module_id');

        $bySection = [];
        foreach ($progresses as $p) {
            if (! $p->lesson) {
                continue;
            }
            $lesson = $p->lesson;
            $wrapper = $wrappers->get($lesson->member_module_id);
            $sectionId = $wrapper?->member_section_id ?? $lesson->module?->member_section_id;
            if ($sectionId === null) {
                continue;
            }
            if (isset($bySection[$sectionId])) {
                continue;
            }
            $bySection[$sectionId] = $p;
        }

        $items = [];
        foreach ($bySection as $p) {
            $lesson = $p->lesson;
            $wrapper = $wrappers->get($lesson->member_module_id);
            $moduleForMeta = $wrapper ?? $lesson->module;
            $moduleThumbnail = null;
            if ($moduleForMeta && $moduleForMeta->thumbnail) {
                $moduleThumbnail = str_starts_with($moduleForMeta->thumbnail, 'http') ? $moduleForMeta->thumbnail : (new StorageService($product->tenant_id))->url($moduleForMeta->thumbnail);
            }
            $items[] = [
                'lesson_id' => $lesson->id,
                'module_id' => $wrapper?->id ?? $lesson->module?->id,
                'title' => $lesson->title,
                'module_title' => $moduleForMeta?->title,
                'module_thumbnail' => $moduleThumbnail,
            ];
        }

        return $items;
    }

    private function memberAreaModuleRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        return str_ends_with($name, '.host') ? 'member-area-app.module.host' : 'member-area-app.module';
    }

    private function memberAreaModulosRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        return str_ends_with($name, '.host') ? 'member-area-app.modulos.host' : 'member-area-app.modulos';
    }

    /**
     * Abertura de "outros produtos" mantendo o contexto da área atual.
     *
     * - Se o produto relacionado é "Link": redireciona para o endpoint deliverable.
     * - Se for área de membros: redireciona para o primeiro módulo embutido (wrapper) dentro do produto host.
     */
    public function openRelatedProduct(Request $request, string $relatedProduct): RedirectResponse
    {
        $host = $this->getProduct($request);
        $user = $request->user();

        $slug = (string) ($request->attributes->get('member_area_slug') ?? $request->route('slug') ?? '');
        $slug = $slug !== '' ? $slug : (string) ($host->checkout_slug ?? '');

        if (! $user instanceof User) {
            return redirect()->route('login')->with('error', 'Faça login para acessar a área de membros.');
        }

        $relatedId = ctype_digit($relatedProduct) ? (int) $relatedProduct : $relatedProduct;
        $related = Product::find($relatedId);
        if (! $related) {
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Produto relacionado não encontrado ou indisponível.');
        }

        if ($related->type === Product::TYPE_LINK) {
            return redirect()->route($this->memberAreaProductsDeliverableRouteName($request), $this->memberAreaProductsRouteParams($request, $slug, $relatedProduct));
        }

        $wrapper = MemberModule::query()
            ->where('product_id', $host->id)
            ->where('related_product_id', $related->id)
            ->whereNotNull('source_member_module_id')
            ->orderBy('position')
            ->first();

        if (! $wrapper) {
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Este produto ainda não foi embutido nesta área. No Member Builder, adicione/importa os módulos do produto para esta seção.');
        }

        $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()->route(
            $this->memberAreaModuleRouteName($request),
            $this->memberAreaModuleRouteParams($request, $slug, (string) $wrapper->id)
        );
    }

    /**
     * Endpoint dedicado para abrir o deliverable de produtos do tipo "Link" a partir da área de membros.
     * Deve ser usado com target=_blank no front.
     */
    public function openRelatedProductDeliverable(Request $request, string $relatedProduct): RedirectResponse
    {
        $host = $this->getProduct($request);
        $user = $request->user();

        $slug = (string) ($request->attributes->get('member_area_slug') ?? $request->route('slug') ?? '');
        $slug = $slug !== '' ? $slug : (string) ($host->checkout_slug ?? '');

        if (! $user instanceof User) {
            return redirect()->route('login')->with('error', 'Faça login para acessar a área de membros.');
        }

        // 1) Tenta resolver via card/wrapper do próprio host (fonte mais confiável do contexto "paid/free")
        $anyWrapperOrCard = MemberModule::query()
            ->with('relatedProduct')
            ->where('product_id', $host->id)
            ->where('related_product_id', $relatedProduct)
            ->orderBy('position')
            ->first();

        $related = $anyWrapperOrCard?->relatedProduct;

        // 2) Fallback: resolver pelo id diretamente (não depende de ter card importado)
        if (! $related) {
            $relatedId = ctype_digit($relatedProduct) ? (int) $relatedProduct : $relatedProduct;
            $related = Product::find($relatedId);
        }

        if (! $related) {
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Produto relacionado não encontrado ou indisponível.');
        }

        // Gate de acesso: se existir card/wrapper no host, respeita paid/free e acesso do usuário.
        if ($anyWrapperOrCard) {
            $redirect = $this->assertEmbeddedProductLinkAccess($anyWrapperOrCard, $user);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        // Abrir deliverable (produto tipo Link)
        if ($related->type === Product::TYPE_LINK) {
            $config = is_array($related->checkout_config) ? $related->checkout_config : [];
            $link = $config['deliverable_link'] ?? '';
            $link = is_string($link) ? trim($link) : '';
            if ($link !== '') {
                return str_starts_with($link, 'http://') || str_starts_with($link, 'https://')
                    ? redirect()->away($link)
                    : redirect('/' . ltrim($link, '/'));
            }
            return redirect()->to($this->baseUrlForRequest($host, $request))
                ->with('error', 'Este produto está como tipo Link, mas o link de entrega não foi configurado.');
        }

        return redirect()->route(
            $this->memberAreaProductsOpenRouteName($request),
            $this->memberAreaProductsRouteParams($request, $slug, $relatedProduct)
        );
    }

    private function memberAreaProductsOpenRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';
        return str_ends_with($name, '.host') ? 'member-area-app.products.open.host' : 'member-area-app.products.open';
    }

    private function memberAreaProductsDeliverableRouteName(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';
        return str_ends_with($name, '.host') ? 'member-area-app.products.deliverable.host' : 'member-area-app.products.deliverable';
    }

    /**
     * @return array<string, mixed>
     */
    private function memberAreaProductsRouteParams(Request $request, string $slug, string $productId): array
    {
        $isHost = str_ends_with(($request->route()?->getName() ?? ''), '.host');
        return $isHost ? ['relatedProduct' => $productId] : ['slug' => $slug, 'relatedProduct' => $productId];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberAreaModuleRouteParams(Request $request, string $slug, string $moduleId): array
    {
        $isHost = str_ends_with(($request->route()?->getName() ?? ''), '.host');
        return $isHost ? ['module' => $moduleId] : ['slug' => $slug, 'module' => $moduleId];
    }

    private function assertEmbeddedProductLinkAccess(MemberModule $wrapper, User $user): ?RedirectResponse
    {
        if (! $wrapper->related_product_id) {
            return null;
        }
        $hasAccess = $user->products()->where('products.id', $wrapper->related_product_id)->exists();
        if (($wrapper->access_type ?? 'paid') === 'paid' && ! $hasAccess) {
            $related = Product::find($wrapper->related_product_id);
            if ($related?->checkout_slug) {
                return redirect()->route('checkout.show', ['slug' => $related->checkout_slug])
                    ->with('error', 'Você não tem acesso a este conteúdo.');
            }
            abort(403);
        }

        // If the embedded product is a "Link" deliverable, open the deliverable link instead of
        // trying to render it as member-area content (which would 404).
        $related = Product::find($wrapper->related_product_id);
        if ($related?->type === Product::TYPE_LINK) {
            $config = is_array($related->checkout_config) ? $related->checkout_config : [];
            $link = $config['deliverable_link'] ?? '';
            $link = is_string($link) ? trim($link) : '';
            if ($link !== '') {
                return str_starts_with($link, 'http://') || str_starts_with($link, 'https://')
                    ? redirect()->away($link)
                    : redirect('/' . ltrim($link, '/'));
            }
        }

        return null;
    }

    /**
     * Módulo de origem (com aulas) quando o registro é um wrapper de embed; senão o próprio módulo.
     */
    private function resolveContentModuleForWrapper(MemberModule $wrapper): MemberModule
    {
        if (! $wrapper->source_member_module_id) {
            if (! $wrapper->relationLoaded('lessons')) {
                $wrapper->load(['lessons' => fn ($q) => $q->orderBy('position')]);
            }

            return $wrapper;
        }
        $source = MemberModule::query()
            ->whereKey($wrapper->source_member_module_id)
            ->where('product_id', $wrapper->related_product_id)
            ->with(['lessons' => fn ($q) => $q->orderBy('position')])
            ->first();
        if (! $source) {
            abort(404);
        }

        return $source;
    }

    private function findWrapperForEmbeddedLesson(MemberLesson $lesson, Product $host): ?MemberModule
    {
        if ((string) $lesson->product_id === (string) $host->id) {
            return null;
        }

        return MemberModule::query()
            ->where('product_id', $host->id)
            ->where('source_member_module_id', $lesson->member_module_id)
            ->where('related_product_id', $lesson->product_id)
            ->first();
    }

    private function mapModuleForMemberArea(MemberModule $m, MemberSection $s, Product $product, $user, array $userProductIds, string $baseUrl, Carbon $accessStartAt, Carbon $now): array
    {
        $sectionType = $s->section_type ?? 'courses';

        if ($sectionType === 'courses') {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'thumbnail' => $m->thumbnail,
                'show_title_on_cover' => $m->show_title_on_cover ?? true,
                ...$this->moduleLockPayload($m, $accessStartAt, $now),
                'lessons' => $m->lessons->map(fn (MemberLesson $l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'type' => $l->type,
                    'duration_seconds' => $l->duration_seconds,
                    'is_completed' => $this->progressService->completedLessonsCount($product, $user) > 0
                        ? $this->isLessonCompleted($user->id, $l->id)
                        : false,
                    ...$this->lessonLockPayload($l, $m, $accessStartAt, $now),
                ])->values()->all(),
            ];
        }

        if ($sectionType === 'products') {
            $related = $m->relatedProduct;
            $hasAccess = $m->related_product_id ? isset($userProductIds[$m->related_product_id]) : false;
            $embed = $m->source_member_module_id
                && $related
                && $related->type === Product::TYPE_AREA_MEMBROS;
            $accessType = $m->access_type ?? 'paid';
            $isFree = $accessType === 'free';
            $canOpenEmbed = $embed && ($isFree || $hasAccess);
            $deliverableLink = null;
            if ($related && $related->type === Product::TYPE_LINK && ($isFree || $hasAccess)) {
                $cfg = is_array($related->checkout_config) ? $related->checkout_config : [];
                $raw = $cfg['deliverable_link'] ?? '';
                $raw = is_string($raw) ? trim($raw) : '';
                $deliverableLink = $raw !== '' ? $raw : null;
            }

            return [
                'id' => $m->id,
                'title' => $m->title,
                'thumbnail' => $m->thumbnail,
                'show_title_on_cover' => $m->show_title_on_cover ?? true,
                'related_product_id' => $m->related_product_id,
                'source_member_module_id' => $m->source_member_module_id,
                'access_type' => $accessType,
                'embed' => $canOpenEmbed,
                'related_product' => $related ? [
                    'id' => $related->id,
                    'name' => $related->name,
                    'type' => $related->type,
                    'deliverable_link' => $deliverableLink,
                    'image_url' => $related->image ? (new StorageService($product->tenant_id))->url($related->image) : null,
                    'checkout_slug' => $related->checkout_slug,
                    'checkout_url' => url('/c/'.$related->checkout_slug),
                    'member_area_slug' => $related->checkout_slug,
                ] : null,
                'has_access' => $hasAccess,
            ];
        }

        // external_links
        return [
            'id' => $m->id,
            'title' => $m->title,
            'thumbnail' => $m->thumbnail,
            'show_title_on_cover' => $m->show_title_on_cover ?? true,
            'external_url' => $m->external_url,
        ];
    }

    private function userAccessStartAt(Product $product, User $user): Carbon
    {
        if ($user->canAccessPanel() && $user->tenant_id === $product->tenant_id) {
            return now()->subYears(20);
        }
        $createdAt = DB::table('product_user')
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->value('created_at');
        if ($createdAt) {
            return Carbon::parse($createdAt);
        }
        return now();
    }

    private function scheduleMeta(?int $afterDays, mixed $atDate, Carbon $accessStartAt): array
    {
        if ($atDate instanceof Carbon) {
            return ['available_at' => $atDate->copy()->startOfDay(), 'mode' => 'date'];
        }
        if (is_string($atDate) && $atDate !== '') {
            return ['available_at' => Carbon::createFromFormat('Y-m-d', $atDate)->startOfDay(), 'mode' => 'date'];
        }
        if (is_int($afterDays) && $afterDays > 0) {
            return ['available_at' => $accessStartAt->copy()->addDays($afterDays), 'mode' => 'days'];
        }
        return ['available_at' => null, 'mode' => null];
    }

    private function lockPayload(?Carbon $availableAt, Carbon $now, ?string $mode): array
    {
        if (! $availableAt) {
            return ['is_locked' => false, 'available_at' => null, 'lock_message' => null];
        }
        if ($availableAt->lessThanOrEqualTo($now)) {
            return ['is_locked' => false, 'available_at' => $availableAt->toIso8601String(), 'lock_message' => null];
        }
        $message = null;
        if ($mode === 'date') {
            $message = 'Disponível em '.$availableAt->format('d/m/Y');
        } elseif ($mode === 'days') {
            // Carbon 3: diffInDays() retorna float (interpolação entre dias); exibir número inteiro.
            $days = max(1, (int) round($now->diffInDays($availableAt, true)));
            $message = $days === 1
                ? 'Disponível em 1 dia'
                : 'Disponível em '.$days.' dias';
        } else {
            $message = 'Disponível em '.$availableAt->format('d/m/Y H:i');
        }
        return ['is_locked' => true, 'available_at' => $availableAt->toIso8601String(), 'lock_message' => $message];
    }

    private function moduleLockPayload(MemberModule $module, Carbon $accessStartAt, Carbon $now): array
    {
        $meta = $this->scheduleMeta($module->release_after_days, $module->release_at_date, $accessStartAt);
        return $this->lockPayload($meta['available_at'], $now, $meta['mode']);
    }

    private function lessonLockPayload(MemberLesson $lesson, ?MemberModule $module, Carbon $accessStartAt, Carbon $now): array
    {
        $lessonMeta = $this->scheduleMeta($lesson->release_after_days, $lesson->release_at_date, $accessStartAt);
        $moduleMeta = $module ? $this->scheduleMeta($module->release_after_days, $module->release_at_date, $accessStartAt) : ['available_at' => null, 'mode' => null];

        $lessonAt = $lessonMeta['available_at'];
        $moduleAt = $moduleMeta['available_at'];
        $availableAt = null;
        $mode = null;
        if ($lessonAt && $moduleAt) {
            if ($lessonAt->greaterThanOrEqualTo($moduleAt)) {
                $availableAt = $lessonAt;
                $mode = $lessonMeta['mode'];
            } else {
                $availableAt = $moduleAt;
                $mode = $moduleMeta['mode'];
            }
        } else {
            if ($lessonAt) {
                $availableAt = $lessonAt;
                $mode = $lessonMeta['mode'];
            } elseif ($moduleAt) {
                $availableAt = $moduleAt;
                $mode = $moduleMeta['mode'];
            }
        }
        return $this->lockPayload($availableAt, $now, $mode);
    }

    private function isLessonCompleted(int $userId, int $lessonId): bool
    {
        return \App\Models\MemberLessonProgress::where('user_id', $userId)
            ->where('member_lesson_id', $lessonId)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * Dados do aluno para marca d'água (nome, email, cpf se houver no pedido).
     */
    private function getStudentWatermarkData(User $user, Product $product): array
    {
        $cpf = Order::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 'completed')
            ->latest()
            ->value('cpf');
        if (is_string($cpf) && trim($cpf) !== '') {
            return ['name' => $user->name ?? '', 'email' => $user->email ?? '', 'cpf' => trim($cpf)];
        }

        return ['name' => $user->name ?? '', 'email' => $user->email ?? '', 'cpf' => null];
    }

    public function manifest(Request $request, ?string $slug = null): \Illuminate\Http\JsonResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404);
        }
        $slug = $slug ?? $request->route('slug') ?? $request->attributes->get('member_area_slug');
        $baseUrl = rtrim($this->baseUrlForRequest($product, $request), '/');
        $config = $product->member_area_config;
        $pwa = $config['pwa'] ?? [];
        $logos = $config['logos'] ?? [];
        $name = $pwa['name'] ?: $product->name;
        $shortName = $pwa['short_name'] ?: $name;
        $themeColor = $pwa['theme_color'] ?? '#0ea5e9';

        $icons = [];
        $faviconUrl = $logos['favicon'] ?? $pwa['favicon'] ?? null;
        if ($faviconUrl) {
            $iconUrl = str_starts_with($faviconUrl, 'http') ? $faviconUrl : (str_starts_with($faviconUrl, '/') ? $request->getSchemeAndHttpHost().$faviconUrl : $request->getSchemeAndHttpHost().'/'.ltrim($faviconUrl, '/'));
            $icons[] = ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'];
            $icons[] = ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
        }
        if (isset($pwa['icons']) && is_array($pwa['icons'])) {
            foreach ($pwa['icons'] as $icon) {
                $src = $icon['src'] ?? null;
                if ($src && ! str_starts_with($src, 'http')) {
                    $src = $request->getSchemeAndHttpHost().(str_starts_with($src, '/') ? $src : '/'.ltrim($src, '/'));
                }
                if ($src) {
                    $icons[] = [
                        'src' => $src,
                        'sizes' => $icon['sizes'] ?? '192x192',
                        'type' => $icon['type'] ?? 'image/png',
                        'purpose' => $icon['purpose'] ?? 'any maskable',
                    ];
                }
            }
        }
        if (empty($icons)) {
            $icons[] = [
                'src' => $request->getSchemeAndHttpHost().'/images/gateways/pix.svg',
                'sizes' => '192x192',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ];
        }

        // `id` único para o Android tratar como app separado do painel (mesmo origin); evita "já está instalado"
        $manifestId = $slug ? '/m/'.$slug : ($baseUrl ? parse_url($baseUrl, PHP_URL_PATH) : '/m/member-area');

        $manifest = [
            'id' => $manifestId,
            'name' => $name,
            'short_name' => $shortName,
            'start_url' => $baseUrl,
            'scope' => $baseUrl.'/',
            'display' => 'standalone',
            'background_color' => $config['theme']['background'] ?? '#18181b',
            'theme_color' => $themeColor,
            'icons' => $icons,
        ];

        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    private function baseUrlForRequest(Product $product, Request $request): string
    {
        $accessType = $request->attributes->get('member_area_access_type');
        if (in_array($accessType, ['subdomain', 'custom'], true)) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return $this->resolver->baseUrlForProduct($product);
    }

    private function assertLessonViewableForPdf(Request $request, MemberLesson $lesson): void
    {
        $product = $this->getProduct($request);
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $lesson->loadMissing('module');
        $wrapper = $this->findWrapperForEmbeddedLesson($lesson, $product);
        if ((string) $lesson->product_id !== (string) $product->id && $wrapper === null) {
            abort(404);
        }
        if ($wrapper !== null) {
            $redirect = $this->assertEmbeddedProductLinkAccess($wrapper, $user);
            if ($redirect !== null) {
                abort(403, 'Sem acesso a este conteúdo.');
            }
        }
        $accessStartAt = $this->userAccessStartAt($product, $user);
        $now = now();
        $effectiveModule = $wrapper !== null
            ? $this->resolveContentModuleForWrapper($wrapper)
            : $lesson->module;
        if ($effectiveModule) {
            $moduleLock = $this->moduleLockPayload($effectiveModule, $accessStartAt, $now);
            if (($moduleLock['is_locked'] ?? false) === true) {
                abort(403, $moduleLock['lock_message'] ?? 'Módulo ainda não liberado.');
            }
        }
        $lessonLock = $this->lessonLockPayload($lesson, $effectiveModule, $accessStartAt, $now);
        if (($lessonLock['is_locked'] ?? false) === true) {
            abort(403, $lessonLock['lock_message'] ?? 'Aula ainda não liberada.');
        }
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    private function sanitizeLessonResourceLinksForResponse(mixed $links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $out = [];
        foreach ($links as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            if ($title === '' || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                continue;
            }
            $out[] = [
                'title' => mb_substr($title, 0, 120),
                'url' => mb_substr($url, 0, 2000),
            ];
        }

        return array_slice($out, 0, 20);
    }

    /**
     * Mesma ordem que `normalizePdfFiles` no frontend (MemberAreaApp).
     *
     * @return list<string>
     */
    private function pdfLessonFileName(MemberLesson $lesson, int $fileIndex): string
    {
        $files = $lesson->content_files;
        if (is_array($files) && isset($files[$fileIndex]) && is_array($files[$fileIndex])) {
            $name = trim((string) ($files[$fileIndex]['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $urls = $this->pdfPresentationSourceUrls($lesson);
        $source = $urls[$fileIndex] ?? '';
        $path = parse_url($source, PHP_URL_PATH);

        return ($path && basename($path) !== '') ? basename($path) : 'documento.pdf';
    }

    private function pdfPresentationSourceUrls(MemberLesson $lesson): array
    {
        $urls = [];
        $files = $lesson->content_files;
        if (is_array($files)) {
            foreach ($files as $it) {
                if (is_string($it)) {
                    $u = trim($it);
                    if ($u !== '') {
                        $urls[] = $u;
                    }
                } elseif (is_array($it)) {
                    $u = trim((string) ($it['url'] ?? ''));
                    if ($u !== '') {
                        $urls[] = $u;
                    }
                }
            }
        }
        if ($urls === [] && $lesson->content_url) {
            $u = trim((string) $lesson->content_url);
            if ($u !== '') {
                $urls[] = $u;
            }
        }

        return $urls;
    }
}
