<?php

namespace App\Http\Controllers;

use App\Services\StorageService;
use App\Support\StudentAreaBranding;
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
        $hubNav = null;
        $studentBranding = null;
        $notificationsUnread = 0;
        if ($user->isAluno()) {
            try {
                if (! $tenantId) {
                    $owned = $user->products()->orderBy('name')->get()->filter(fn ($p) => $p->type === \App\Models\Product::TYPE_AREA_MEMBROS);
                    $tenantId = (int) ($owned->first()?->tenant_id ?? 0) ?: null;
                }
                $hubNav = [
                    'community_enabled' => \App\Support\StudentAreaSettings::communityEnabled($tenantId),
                    'certificate_enabled' => \App\Support\StudentAreaSettings::certificateEnabled($tenantId),
                    'gamification_enabled' => \App\Support\StudentAreaSettings::gamificationEnabled($tenantId),
                ];
            } catch (\Throwable) {}

            // Branding for student sidebar (same source as /area-membros).
            try {
                $studentBranding = StudentAreaBranding::forUser($user);
            } catch (\Throwable) {
                $studentBranding = StudentAreaBranding::forTenant(null);
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
            'hub_nav' => $hubNav,
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
