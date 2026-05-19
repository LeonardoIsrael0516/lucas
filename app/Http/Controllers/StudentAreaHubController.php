<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCommunityPagePayload;
use App\Http\Controllers\Concerns\SharesStudentSupportProps;
use App\Models\MemberCertificateIssued;
use App\Models\MemberCommunityPage;
use App\Models\MemberCommunityPost;
use App\Models\MemberCommunityPostComment;
use App\Models\MemberCommunityPostLike;
use App\Models\Product;
use App\Services\GamificationService;
use App\Services\MemberProgressService;
use App\Services\StorageService;
use App\Support\StudentAreaBranding;
use App\Support\StudentAreaSettings;
use App\Support\StudentAreaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentAreaHubController extends Controller
{
    use BuildsCommunityPagePayload;
    use SharesStudentSupportProps;

    public function __construct(
        protected MemberProgressService $progressService,
        protected GamificationService $gamificationService,
    ) {}

    private function hubProps(Request $request, array $extra = []): array
    {
        $user = $request->user();
        $tenantId = StudentAreaTenant::idForUser($user);

        return array_merge([
            'student_branding' => StudentAreaBranding::forTenant($tenantId),
            'hub_nav' => [
                'community_enabled' => StudentAreaSettings::communityEnabled($tenantId),
                'certificate_enabled' => StudentAreaSettings::certificateEnabled($tenantId),
                'gamification_enabled' => StudentAreaSettings::gamificationEnabled($tenantId),
            ],
            'profile_href' => route('profile.index'),
            'suporte_href' => route('student-support.index'),
        ], $this->studentSupportPayload($tenantId), $extra);
    }

    public function comunidade(Request $request): Response|RedirectResponse
    {
        $tenantId = StudentAreaTenant::idForUser($request->user());
        if (! StudentAreaSettings::communityEnabled($tenantId)) {
            return redirect()->route('member-area.index')->with('error', 'Comunidade não está disponível.');
        }
        $pages = MemberCommunityPage::query()->forTenant($tenantId)->orderBy('position')->get();
        $defaultPage = $pages->firstWhere('is_default', true) ?? $pages->first();
        if ($defaultPage) {
            return redirect()->route('student-hub.comunidade.page', ['pageSlug' => $defaultPage->slug]);
        }

        return Inertia::render('MemberArea/Comunidade', $this->hubProps($request, [
            'pages' => [],
        ]));
    }

    public function comunidadePage(Request $request, string $pageSlug): Response
    {
        $user = $request->user();
        $tenantId = StudentAreaTenant::idForUser($user);
        if (! StudentAreaSettings::communityEnabled($tenantId)) {
            abort(404);
        }
        $pages = MemberCommunityPage::query()->forTenant($tenantId)->orderBy('position')->get();
        $page = $pages->firstWhere('slug', $pageSlug);
        if (! $page) {
            abort(404);
        }
        $posts = $page->posts()->with(['user:id,name,email,avatar', 'likes', 'comments.user:id,name,avatar'])->latest()->paginate(20);
        $usersCanDeleteOwn = StudentAreaSettings::communityUsersCanDeleteOwnPosts($tenantId);
        $posts->getCollection()->transform(function (MemberCommunityPost $post) use ($user, $tenantId) {
            $storage = new StorageService($tenantId);

            return array_merge($post->toArray(), [
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'avatar_url' => $post->user->avatar ? $storage->url($post->user->avatar) : null,
                ] : null,
                'image_url' => $post->image ? $storage->url($post->image) : null,
                'likes_count' => $post->likes->count(),
                'user_has_liked' => $post->likes->contains('user_id', $user->id),
                'comments' => $post->comments->map(fn (MemberCommunityPostComment $c) => [
                    'id' => $c->id,
                    'content' => $c->content,
                    'created_at' => $c->created_at->format('d/m/Y H:i'),
                    'user' => $c->user ? [
                        'id' => $c->user->id,
                        'name' => $c->user->name,
                        'avatar_url' => $c->user->avatar ? $storage->url($c->user->avatar) : null,
                    ] : null,
                ])->values()->all(),
            ]);
        });

        return Inertia::render('MemberArea/ComunidadePage', $this->hubProps($request, [
            'auth_user_id' => $user->id,
            'community_users_can_delete_own_posts' => $usersCanDeleteOwn,
            'pages' => $pages->map(fn ($p) => $this->communityPageToArray($p, $tenantId))->values()->all(),
            'page' => $this->communityPageToArray($page, $tenantId),
            'posts' => $posts,
        ]));
    }

    public function storeCommunityPost(Request $request, string $pageSlug): RedirectResponse
    {
        $tenantId = StudentAreaTenant::idForUser($request->user());
        $page = MemberCommunityPage::query()->forTenant($tenantId)->where('slug', $pageSlug)->firstOrFail();
        if (! $page->is_public_posting && ! $request->user()->canAccessPanel()) {
            abort(403);
        }
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'image', 'max:5120'],
        ]);
        $imagePath = null;
        if ($request->hasFile('image')) {
            $storage = new StorageService($tenantId);
            $imagePath = $storage->putFile('member-area-posts/global', $request->file('image'));
        }
        MemberCommunityPost::create([
            'member_community_page_id' => $page->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'image' => $imagePath,
        ]);

        return back()->with('success', 'Post publicado.');
    }

    public function destroyCommunityPost(Request $request, string $pageSlug, MemberCommunityPost $post): RedirectResponse
    {
        $tenantId = StudentAreaTenant::idForUser($request->user());
        $page = MemberCommunityPage::query()->forTenant($tenantId)->where('slug', $pageSlug)->firstOrFail();
        if ($post->member_community_page_id !== $page->id) {
            abort(404);
        }
        $user = $request->user();
        $isAuthor = $post->user_id === $user->id;
        $canDeleteAny = $user->canAccessPanel() && (int) $user->tenant_id === $tenantId;
        if (! $canDeleteAny && ! ($isAuthor && StudentAreaSettings::communityUsersCanDeleteOwnPosts($tenantId))) {
            abort(403);
        }
        $post->delete();

        return back()->with('success', 'Postagem excluída.');
    }

    public function likeCommunityPost(Request $request, string $pageSlug, MemberCommunityPost $post): JsonResponse
    {
        $this->assertPostOnTenantPage($request, $pageSlug, $post);
        MemberCommunityPostLike::firstOrCreate([
            'member_community_post_id' => $post->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['likes_count' => $post->likes()->count(), 'user_has_liked' => true]);
    }

    public function unlikeCommunityPost(Request $request, string $pageSlug, MemberCommunityPost $post): JsonResponse
    {
        $this->assertPostOnTenantPage($request, $pageSlug, $post);
        MemberCommunityPostLike::where('member_community_post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['likes_count' => $post->likes()->count(), 'user_has_liked' => false]);
    }

    public function storeCommunityPostComment(Request $request, string $pageSlug, MemberCommunityPost $post): JsonResponse|RedirectResponse
    {
        $this->assertPostOnTenantPage($request, $pageSlug, $post);
        $validated = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $comment = MemberCommunityPostComment::create([
            'member_community_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);
        $comment->load('user:id,name,avatar');
        $tenantId = StudentAreaTenant::idForUser($request->user());
        if ($request->expectsJson()) {
            return response()->json([
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at->format('d/m/Y H:i'),
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'avatar_url' => $comment->user->avatar ? (new StorageService($tenantId))->url($comment->user->avatar) : null,
                    ] : null,
                ],
            ]);
        }

        return back()->with('success', 'Comentário adicionado.');
    }

    public function certificados(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $tenantId = StudentAreaTenant::idForUser($user);
        if (! StudentAreaSettings::certificateEnabled($tenantId)) {
            return redirect()->route('member-area.index')->with('error', 'Certificados não estão disponíveis.');
        }
        $certTemplate = StudentAreaSettings::certificateConfig($tenantId);
        $items = [];
        $products = $user->products()
            ->where('type', Product::TYPE_AREA_MEMBROS)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $required = StudentAreaSettings::certificateCompletionPercentForProduct($product);
            $percent = $this->progressService->completionPercent($product, $user);
            $issued = MemberCertificateIssued::where('user_id', $user->id)->where('product_id', $product->id)->first();
            if ($percent >= $required && ! $issued) {
                $issued = $this->progressService->issueCertificate($product, $user);
            }
            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'progress_percent' => $percent,
                'required_percent' => $required,
                'issued' => $issued !== null,
                'issued_at' => $issued?->issued_at?->format('d/m/Y'),
                'view_url' => $issued ? route('student-hub.certificado.show', ['product' => $product->id]) : null,
            ];
        }

        return Inertia::render('MemberArea/Certificados', $this->hubProps($request, [
            'certificate_template' => $certTemplate,
            'certificates' => $items,
        ]));
    }

    public function certificadoShow(Request $request, Product $product): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user->products()->where('products.id', $product->id)->exists()) {
            abort(403);
        }
        $tenantId = StudentAreaTenant::idForUser($user);
        $certConfig = StudentAreaSettings::certificateConfig($tenantId);
        if (empty($certConfig['enabled'])) {
            return redirect()->route('student-hub.certificados');
        }
        $issued = MemberCertificateIssued::where('user_id', $user->id)->where('product_id', $product->id)->first();
        if (! $issued) {
            return redirect()->route('student-hub.certificados')->with('error', 'Certificado ainda não disponível.');
        }
        $certTitle = ! empty($certConfig['title']) ? $certConfig['title'] : $product->name;

        return Inertia::render('MemberArea/CertificadoShow', $this->hubProps($request, [
            'product' => ['id' => $product->id, 'name' => $product->name],
            'certificate' => [
                'title' => $certTitle,
                'issued_at' => $issued->issued_at->format('d/m/Y'),
                'completion_percent' => $issued->completion_percent,
                'student_name' => $user->name,
                'config' => $certConfig,
            ],
        ]));
    }

    public function conquistas(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $tenantId = StudentAreaTenant::idForUser($user);
        if (! StudentAreaSettings::gamificationEnabled($tenantId)) {
            return redirect()->route('member-area.index')->with('error', 'Conquistas não estão disponíveis.');
        }
        $achievements = $tenantId
            ? $this->gamificationService->getAllAchievementsForUserInTenant($user, $tenantId)
            : [];

        return Inertia::render('MemberArea/Conquistas', $this->hubProps($request, [
            'achievements' => $achievements,
        ]));
    }

    private function assertPostOnTenantPage(Request $request, string $pageSlug, MemberCommunityPost $post): void
    {
        $tenantId = StudentAreaTenant::idForUser($request->user());
        $page = MemberCommunityPage::query()->forTenant($tenantId)->where('slug', $pageSlug)->firstOrFail();
        if ($post->member_community_page_id !== $page->id) {
            abort(404);
        }
    }
}
