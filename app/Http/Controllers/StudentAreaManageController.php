<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsCommunityPagePayload;
use App\Models\MemberCommunityPage;
use App\Models\Setting;
use App\Services\StorageService;
use App\Support\SchoolLoginSettings;
use App\Support\StudentAreaSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentAreaManageController extends Controller
{
    use BuildsCommunityPagePayload;

    private function tenantId(): ?int
    {
        $id = auth()->user()?->tenant_id;

        return $id ? (int) $id : null;
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('area-aluno.personalizacao');
    }

    public function personalizacao(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('AreaAluno/Personalizacao', [
            'active_section' => 'personalizacao',
            'student_area_primary' => (string) Setting::get('student_area_primary', '#0ea5e9', $tenantId),
        ]);
    }

    public function updatePersonalizacao(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $request->validate([
            'student_area_primary' => ['nullable', 'string', 'max:32'],
        ]);

        if ($request->filled('student_area_primary')) {
            Setting::set('student_area_primary', $request->input('student_area_primary'), $tenantId);
        }

        return back()->with('success', 'Personalização salva.');
    }

    public function login(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('AreaAluno/Login', [
            'active_section' => 'login',
            'login' => $this->loginSettingsPayload($tenantId),
        ]);
    }

    public function updateLogin(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $request->validate([
            'login_title' => ['nullable', 'string', 'max:255'],
            'login_subtitle' => ['nullable', 'string', 'max:255'],
            'login_background_color' => ['nullable', 'string', 'max:32'],
            'login_primary_color' => ['nullable', 'string', 'max:32'],
            'login_password_mode' => ['nullable', 'string', 'in:auto,default'],
            'login_default_password' => ['nullable', 'string', 'max:255'],
            'login_without_password' => ['sometimes', 'boolean'],
            'login_logo' => ['nullable', 'image', 'max:2048'],
            'login_background_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->persistLoginSettings($request, $tenantId);

        return back()->with('success', 'Tela de login salva.');
    }

    public function suporte(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('AreaAluno/Suporte', [
            'active_section' => 'suporte',
            'student_support_enabled' => Setting::get('student_support_enabled', '0', $tenantId) === '1',
            'student_support_whatsapp_enabled' => Setting::get('student_support_whatsapp_enabled', '0', $tenantId) === '1',
            'student_support_whatsapp_url' => (string) Setting::get('student_support_whatsapp_url', '', $tenantId),
        ]);
    }

    public function updateSuporte(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $request->validate([
            'student_support_enabled' => ['sometimes', 'boolean'],
            'student_support_whatsapp_enabled' => ['sometimes', 'boolean'],
            'student_support_whatsapp_url' => ['nullable', 'string', 'max:512'],
        ]);

        if ($request->boolean('student_support_whatsapp_enabled')) {
            $wa = trim((string) $request->input('student_support_whatsapp_url', ''));
            if ($wa === '' || ! filter_var($wa, FILTER_VALIDATE_URL)) {
                return back()->withErrors(['student_support_whatsapp_url' => 'Informe um link válido do WhatsApp.'])->withInput();
            }
        }

        Setting::set('student_support_enabled', $request->boolean('student_support_enabled') ? '1' : '0', $tenantId);
        Setting::set('student_support_whatsapp_enabled', $request->boolean('student_support_whatsapp_enabled') ? '1' : '0', $tenantId);
        Setting::set('student_support_whatsapp_url', trim((string) $request->input('student_support_whatsapp_url', '')), $tenantId);

        return back()->with('success', 'Suporte salvo.');
    }

    public function comunidade(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('AreaAluno/Comunidade', [
            'active_section' => 'comunidade',
            'community_enabled' => StudentAreaSettings::communityEnabled($tenantId),
            'community_users_can_delete_own_posts' => StudentAreaSettings::communityUsersCanDeleteOwnPosts($tenantId),
            'community_pages' => $tenantId ? $this->communityPagesPayloadForTenant($tenantId) : [],
        ]);
    }

    public function updateComunidadeSettings(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $request->validate([
            'community_enabled' => ['sometimes', 'boolean'],
            'community_users_can_delete_own_posts' => ['sometimes', 'boolean'],
        ]);
        StudentAreaSettings::setCommunityEnabled($request->boolean('community_enabled'), $tenantId);
        StudentAreaSettings::setCommunityUsersCanDeleteOwnPosts($request->boolean('community_users_can_delete_own_posts'), $tenantId);

        return back()->with('success', 'Configurações da comunidade salvas.');
    }

    public function storeCommunityPage(Request $request): JsonResponse|RedirectResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:255'],
            'banner' => ['nullable', 'string', 'max:500'],
            'is_public_posting' => ['boolean'],
            'is_default' => ['boolean'],
        ]);
        if ($request->boolean('is_default')) {
            MemberCommunityPage::forTenant($tenantId)->update(['is_default' => false]);
        }
        $max = (int) (MemberCommunityPage::forTenant($tenantId)->max('position') ?? 0);
        MemberCommunityPage::create([
            'tenant_id' => $tenantId,
            'product_id' => null,
            'title' => $validated['title'],
            'icon' => $validated['icon'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'banner' => $validated['banner'] ?? null,
            'position' => $max + 1,
            'is_public_posting' => $request->boolean('is_public_posting', true),
            'is_default' => $request->boolean('is_default', false),
        ]);

        return $this->communityPagesResponse($tenantId, 'Página criada.');
    }

    public function updateCommunityPage(Request $request, MemberCommunityPage $page): JsonResponse|RedirectResponse
    {
        $tenantId = $this->tenantId();
        if ((int) $page->tenant_id !== $tenantId) {
            abort(404);
        }
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:255'],
            'banner' => ['nullable', 'string', 'max:500'],
            'is_public_posting' => ['boolean'],
            'is_default' => ['boolean'],
        ]);
        if ($request->boolean('is_default')) {
            MemberCommunityPage::forTenant($tenantId)->where('id', '!=', $page->id)->update(['is_default' => false]);
        }
        $page->update($validated);

        return $this->communityPagesResponse($tenantId, 'Página atualizada.');
    }

    public function destroyCommunityPage(Request $request, MemberCommunityPage $page): JsonResponse|RedirectResponse
    {
        $tenantId = $this->tenantId();
        if ((int) $page->tenant_id !== $tenantId) {
            abort(404);
        }
        $page->delete();

        return $this->communityPagesResponse($tenantId, 'Página removida.');
    }

    public function uploadCommunityBanner(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $request->validate(['file' => ['required', 'file', 'image', 'max:5120']]);
        $storage = new StorageService($tenantId);
        $path = $storage->putFile('member-area-community', $request->file('file'));

        return response()->json(['path' => $path, 'url' => $storage->url($path)]);
    }

    public function certificado(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('AreaAluno/Certificado', [
            'active_section' => 'certificado',
            'certificate' => StudentAreaSettings::certificateConfig($tenantId),
        ]);
    }

    public function updateCertificado(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validate([
            'certificate' => ['required', 'array'],
            'certificate.enabled' => ['boolean'],
            'certificate.title' => ['nullable', 'string', 'max:255'],
            'certificate.completion_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'certificate.signature_text' => ['nullable', 'string', 'max:500'],
            'certificate.platform_name' => ['nullable', 'string', 'max:255'],
            'certificate.primary_color' => ['nullable', 'string', 'max:32'],
            'certificate.background_image_url' => ['nullable', 'string', 'max:2048'],
        ]);
        $cert = array_replace_recursive(StudentAreaSettings::defaultCertificateConfig(), $validated['certificate']);
        StudentAreaSettings::setCertificateConfig($cert, $tenantId);

        return back()->with('success', 'Certificado salvo.');
    }

    public function gamificacao(): Response
    {
        $tenantId = $this->tenantId();

        return Inertia::render('AreaAluno/Gamificacao', [
            'active_section' => 'gamificacao',
            'gamification' => StudentAreaSettings::gamificationConfig($tenantId),
        ]);
    }

    public function updateGamificacao(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validate([
            'gamification' => ['required', 'array'],
            'gamification.enabled' => ['boolean'],
            'gamification.achievements' => ['nullable', 'array'],
        ]);
        $g = $validated['gamification'];
        if (isset($g['achievements']) && is_array($g['achievements'])) {
            foreach ($g['achievements'] as $i => $ach) {
                if (empty($ach['id'])) {
                    $g['achievements'][$i]['id'] = (string) Str::uuid();
                }
            }
            $g['achievements'] = array_values($g['achievements']);
        }
        StudentAreaSettings::setGamificationConfig($g, $tenantId);

        return back()->with('success', 'Gamificação salva.');
    }

    public function uploadGamificationBadge(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $request->validate(['file' => ['required', 'file', 'image', 'max:5120']]);
        $storage = new StorageService($tenantId);
        $path = $storage->putFile('member-area-gamification', $request->file('file'));

        return response()->json(['path' => $path, 'url' => $storage->url($path)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loginSettingsPayload(?int $tenantId): array
    {
        $login = SchoolLoginSettings::brandingForTenant($tenantId);
        $login['login_password_mode'] = (string) Setting::get('login_password_mode', 'auto', $tenantId);
        $login['login_default_password'] = (string) Setting::get('login_default_password', '', $tenantId);

        return $login;
    }

    private function persistLoginSettings(Request $request, ?int $tenantId): void
    {
        foreach (['login_title', 'login_subtitle', 'login_background_color', 'login_primary_color', 'login_password_mode', 'login_default_password'] as $key) {
            if ($request->has($key)) {
                Setting::set($key, (string) $request->input($key, ''), $tenantId);
            }
        }
        Setting::set('login_without_password', $request->boolean('login_without_password') ? '1' : '0', $tenantId);

        if ($request->hasFile('login_logo')) {
            $storage = new StorageService($tenantId);
            Setting::set('login_logo', $storage->putFile('branding', $request->file('login_logo')), $tenantId);
        }
        if ($request->hasFile('login_background_image')) {
            $storage = new StorageService($tenantId);
            Setting::set('login_background_image', $storage->putFile('branding', $request->file('login_background_image')), $tenantId);
        }
    }

    private function communityPagesResponse(?int $tenantId, string $message): JsonResponse|RedirectResponse
    {
        $pages = $tenantId ? $this->communityPagesPayloadForTenant($tenantId) : [];
        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'community_pages' => $pages]);
        }

        return back()->with('success', $message);
    }
}
