<?php

namespace App\Http\Controllers;

use App\Events\MemberAreaLoaded;
use App\Models\MemberActivityLog;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\MemberModule;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\MemberAreaResolver;
use App\Services\MemberProgressService;
use App\Services\StorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberAreaController extends Controller
{
    use Concerns\SharesStudentSupportProps;

    public function __construct(
        private MemberAreaResolver $resolver,
        private MemberProgressService $memberProgressService,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        $q = trim((string) request()->query('q', ''));
        $ownedProducts = $user->products()
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $p) => $p->type === Product::TYPE_AREA_MEMBROS);

        event(new MemberAreaLoaded($user, $ownedProducts));

        $myCourses = [];
        foreach ($ownedProducts as $product) {
            if (! $product->checkout_slug) {
                continue;
            }
            if ($q !== '' && mb_stripos($product->name, $q, 0, 'UTF-8') === false) {
                continue;
            }
            $myCourses[] = $this->buildCourseCard($product, $user);
        }

        $continueItems = $this->buildContinueSection($user, $q);

        // Alguns bancos legados podem ter aluno com tenant_id null.
        // A personalização do aluno é por tenant, então derivamos pelo primeiro produto acessível quando necessário.
        $tenantId = $user->tenant_id;
        if (! $tenantId) {
            $tenantId = (int) ($ownedProducts->first()?->tenant_id ?? 0) ?: null;
        }
        $studentBranding = [
            'primary' => (string) Setting::get('student_area_primary', '#0ea5e9', $tenantId),
            'logo_url' => null,
        ];
        $logoPath = (string) Setting::get('student_area_logo', '', $tenantId);
        if ($logoPath !== '') {
            try {
                $storage = new StorageService($tenantId);
                $studentBranding['logo_url'] = $storage->exists($logoPath) ? $storage->url($logoPath) : null;
            } catch (\Throwable) {
                $studentBranding['logo_url'] = null;
            }
        }
        $ownedIds = $ownedProducts->pluck('id')->all();
        $otherQuery = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->where('is_active', true)
            ->when($ownedIds !== [], fn ($qq) => $qq->whereNotIn('id', $ownedIds))
            ->orderBy('name');
        if ($q !== '') {
            $otherQuery->where('name', 'like', '%'.$q.'%');
        }
        $otherCourses = [];
        foreach ($otherQuery->get() as $product) {
            if (! $product->checkout_slug) {
                continue;
            }
            $otherStorage = new StorageService($product->tenant_id);
            $otherCourses[] = [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image ? $otherStorage->url($product->image) : null,
                'price_label' => $this->formatMoney((float) $product->price, $product->currency ?? 'BRL'),
                'checkout_url' => route('checkout.show', ['slug' => $product->checkout_slug]),
            ];
        }

        $communityHref = $this->firstCommunityHref($ownedProducts, $user);

        return Inertia::render('MemberArea/Dashboard', array_merge([
            'search_query' => $q,
            'auth_user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $this->initials($user->name ?? ''),
            ],
            'notifications_unread_count' => $this->safeUnreadNotificationsCount($user),
            'continue_items' => $continueItems,
            'my_courses' => $myCourses,
            'other_courses' => $otherCourses,
            'community_href' => $communityHref,
            'profile_href' => route('profile.index'),
            'student_branding' => $studentBranding,
        ], $this->studentSupportPayload($tenantId)));
    }

    private function buildCourseCard(Product $product, $user): array
    {
        $storage = new StorageService($product->tenant_id);
        $baseUrl = rtrim($this->resolver->baseUrlForProduct($product), '/');
        $expiry = $this->accessExpiry($product, $user);
        $pdfStats = $this->pdfMaterialStats($product, $user);
        $newBadge = $this->hasNewContent($product, $user);
        $lastLessonUrl = $this->lastLessonUrlForProduct($product, $user, $baseUrl);
        $percent = $this->memberProgressService->completionPercent($product, $user);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'image_url' => $product->image ? $storage->url($product->image) : null,
            'access_until_label' => $expiry ? 'Acesso até '.$expiry->format('m/Y') : null,
            'apostilas_label' => $pdfStats['total'] > 0
                ? $pdfStats['total'].' '.($pdfStats['total'] === 1 ? 'apostila' : 'apostilas')
                : null,
            'download_progress' => $pdfStats['total'] > 0 ? ['done' => $pdfStats['completed'], 'total' => $pdfStats['total']] : null,
            'completion_percent' => $percent,
            'has_new_content' => $newBadge,
            'continue_href' => $lastLessonUrl,
            'member_area_href' => $baseUrl,
        ];
    }

    private function buildContinueSection($user, string $q): array
    {
        $logs = MemberActivityLog::query()
            ->where('user_id', $user->id)
            ->where('event', 'member_area.lesson_view')
            ->orderByDesc('created_at')
            ->limit(80)
            ->get();

        $seenProductIds = [];
        $items = [];
        foreach ($logs as $log) {
            $productId = $log->product_id;
            if (! $productId || isset($seenProductIds[$productId])) {
                continue;
            }
            $meta = is_array($log->metadata) ? $log->metadata : [];
            $lessonId = $meta['lesson_id'] ?? null;
            if (! $lessonId) {
                continue;
            }
            $product = Product::find($productId);
            if (! $product || $product->type !== Product::TYPE_AREA_MEMBROS) {
                continue;
            }
            if (! $user->products()->where('products.id', $product->id)->exists()) {
                continue;
            }
            if ($q !== '' && mb_stripos($product->name, $q, 0, 'UTF-8') === false) {
                continue;
            }
            $seenProductIds[$productId] = true;

            $lesson = MemberLesson::query()->with(['module.section'])->find($lessonId);
            if (! $lesson) {
                continue;
            }

            $storage = new StorageService($product->tenant_id);
            [$lessonIndex, $lessonTotal] = $this->lessonPositionLabel($product, $lesson->id);
            $baseUrl = rtrim($this->resolver->baseUrlForProduct($product), '/');
            $lessonHref = $baseUrl.'/aula/'.$lesson->id;

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'lesson_id' => $lesson->id,
                'lesson_title' => $lesson->title,
                'module_title' => $lesson->module?->title,
                'section_title' => $lesson->module?->section?->title,
                'lesson_index' => $lessonIndex,
                'lesson_total' => $lessonTotal,
                'page_current' => null,
                'page_total' => null,
                'lesson_href' => $lessonHref,
                'thumbnail_url' => $product->image ? $storage->url($product->image) : null,
            ];

            if (count($items) >= 9) {
                break;
            }
        }

        return $items;
    }

    private function lessonPositionLabel(Product $product, int|string $lessonId): array
    {
        $ordered = $this->collectOrderedLessonIds($product);
        $total = count($ordered);
        $idx = array_search((int) $lessonId, array_map('intval', $ordered), true);
        if ($idx === false) {
            $fallbackTotal = $this->memberProgressService->totalLessonsCount($product);

            return [null, $fallbackTotal > 0 ? $fallbackTotal : null];
        }

        return [$idx + 1, $total];
    }

    private function collectOrderedLessonIds(Product $product): array
    {
        $sections = $product->memberSections()
            ->with(['modules' => fn ($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get();

        $ids = [];
        foreach ($sections as $section) {
            foreach ($section->modules as $module) {
                $effective = $module->source_member_module_id
                    ? MemberModule::query()->with(['lessons' => fn ($q) => $q->orderBy('position')])->find($module->source_member_module_id)
                    : $module;
                if (! $effective) {
                    continue;
                }
                foreach ($effective->lessons->sortBy('position') as $lesson) {
                    $ids[] = (int) $lesson->id;
                }
            }
        }

        return $ids;
    }

    private function pdfMaterialStats(Product $product, $user): array
    {
        $lessonIds = MemberLesson::query()
            ->where('product_id', $product->id)
            ->where('type', MemberLesson::TYPE_PDF)
            ->pluck('id')
            ->all();

        $total = count($lessonIds);
        if ($total === 0) {
            return ['completed' => 0, 'total' => 0];
        }

        $completed = MemberLessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('member_lesson_id', $lessonIds)
            ->whereNotNull('completed_at')
            ->count();

        return ['completed' => $completed, 'total' => $total];
    }

    private function accessExpiry(Product $product, $user): ?Carbon
    {
        $dates = [];

        $orderEnd = Order::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 'completed')
            ->whereNotNull('period_end')
            ->orderByDesc('period_end')
            ->value('period_end');
        if ($orderEnd) {
            $dates[] = Carbon::parse($orderEnd)->endOfDay();
        }

        $subEnd = Subscription::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->orderByDesc('current_period_end')
            ->value('current_period_end');
        if ($subEnd) {
            $dates[] = Carbon::parse($subEnd)->endOfDay();
        }

        if ($dates === []) {
            return null;
        }

        return collect($dates)->sortDesc()->first();
    }

    private function hasNewContent(Product $product, $user): bool
    {
        $lastOpen = MemberActivityLog::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('event', 'member_area.open')
            ->orderByDesc('created_at')
            ->first();

        $since = $lastOpen?->created_at;
        if (! $since) {
            $since = DB::table('product_user')
                ->where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->value('created_at');
            $since = $since ? Carbon::parse($since) : null;
        }
        if (! $since) {
            return false;
        }

        return MemberLesson::query()
            ->where('product_id', $product->id)
            ->where('updated_at', '>', $since)
            ->exists();
    }

    private function lastLessonUrlForProduct(Product $product, $user, string $baseUrl): string
    {
        $log = MemberActivityLog::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('event', 'member_area.lesson_view')
            ->orderByDesc('created_at')
            ->first();

        if ($log && is_array($log->metadata) && ! empty($log->metadata['lesson_id'])) {
            return $baseUrl.'/aula/'.$log->metadata['lesson_id'];
        }

        return $baseUrl;
    }

    private function firstCommunityHref($ownedProducts, $user): ?string
    {
        foreach ($ownedProducts as $product) {
            $config = $product->member_area_config ?? [];
            if (! (bool) ($config['community_enabled'] ?? false)) {
                continue;
            }
            if (! $product->checkout_slug) {
                continue;
            }
            if (! $product->hasMemberAreaAccess($user)) {
                continue;
            }
            $base = rtrim($this->resolver->baseUrlForProduct($product), '/');

            return $base.'/comunidade';
        }

        return null;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\\s+/', trim($name)) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
        }
        if ($parts !== []) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        return '?';
    }

    private function formatMoney(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);

        return 'A partir de '.($currency === 'BRL' ? 'R$ ' : $currency.' ').number_format($amount, 2, ',', '.');
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
}
