<?php

namespace App\Http\Controllers;

use App\Services\StorageService;
use App\Support\StudentAreaTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use Concerns\SharesStudentSupportProps;

    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $component = $user->isAluno() ? 'MemberArea/Profile' : 'Profile/Index';
        $tenantId = $user->tenant_id;
        $communityHref = null;
        $studentBranding = null;
        $notificationsUnread = 0;
        if ($user->isAluno()) {
            // Community link: first product with community enabled.
            try {
                $owned = $user->products()->orderBy('name')->get()->filter(fn ($p) => $p->type === \App\Models\Product::TYPE_AREA_MEMBROS);
                foreach ($owned as $p) {
                    $cfg = $p->member_area_config ?? [];
                    if (! (bool) ($cfg['community_enabled'] ?? false)) continue;
                    if (! $p->hasMemberAreaAccess($user)) continue;
                    $base = rtrim(app(\App\Services\MemberAreaResolver::class)->baseUrlForProduct($p), '/');
                    $communityHref = $base.'/comunidade';
                    break;
                }
            } catch (\Throwable) {}

            // Branding for student sidebar (same source as /area-membros).
            try {
                if (! $tenantId) {
                    $tenantId = (int) ($owned->first()?->tenant_id ?? 0) ?: null;
                }
                $studentBranding = [
                    'primary' => (string) \App\Models\Setting::get('student_area_primary', '#0ea5e9', $tenantId),
                    'logo_url' => null,
                ];
                $logoPath = (string) \App\Models\Setting::get('student_area_logo', '', $tenantId);
                if ($logoPath !== '') {
                    $storage = new \App\Services\StorageService($tenantId);
                    $studentBranding['logo_url'] = $storage->exists($logoPath) ? $storage->url($logoPath) : null;
                }
            } catch (\Throwable) {
                $studentBranding = ['primary' => '#0ea5e9', 'logo_url' => null];
            }

            // Notifications (optional table).
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('notifications') && method_exists($user, 'unreadNotifications')) {
                    $notificationsUnread = (int) $user->unreadNotifications()->count();
                }
            } catch (\Throwable) {}
        }

        $supportExtras = [];
        if ($user->isAluno()) {
            $supportExtras = $this->studentSupportPayload(StudentAreaTenant::idForUser($user));
        }

        return Inertia::render($component, array_merge([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'avatar_url' => $user->avatar ? app(StorageService::class)->url($user->avatar) : null,
            ],
            'community_href' => $communityHref,
            'student_branding' => $studentBranding,
            'notifications_unread_count' => $notificationsUnread,
        ], $supportExtras));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ], [
            'email.unique' => 'Este e-mail já está em uso por outra conta.',
            'username.unique' => 'Este nome de usuário já está em uso.',
        ]);

        $user->name = $validated['name'];
        $user->username = $validated['username'] ?: null;
        if ($user->email !== $validated['email']) {
            $user->email = $validated['email'];
            $user->email_verified_at = null;
        }

        if ($request->hasFile('avatar')) {
            $storage = app(StorageService::class);
            if ($user->avatar && $storage->exists($user->avatar)) {
                $storage->delete($user->avatar);
            }
            $user->avatar = $storage->putFile('avatars', $request->file('avatar'));
        }

        $user->save();

        return redirect()->route('profile.index')->with('success', 'Perfil atualizado.');
    }

    public function updateUsername(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:64', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
        ], [
            'username.unique' => 'Este nome de usuário já está em uso.',
        ]);

        $user->username = $validated['username'] ?: null;
        $user->save();

        return back()->with('success', 'Nome de usuário atualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Informe a senha atual.',
            'password.required' => 'O campo nova senha é obrigatório.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'A senha atual está incorreta.']);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return redirect()->route('profile.index')->with('success', 'Senha alterada.');
    }
}
