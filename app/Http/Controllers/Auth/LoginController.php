<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\TeamAuditLog;
use App\Support\DockerSetupState;
use App\Support\SchoolLoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Tela de login única da escola (configuração global em Configurações).
     */
    public function showLoginForm(Request $request): Response|RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        if (User::count() === 0) {
            return redirect()->route('criar-admin');
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            if ($user && $user->tenant_id && $user->canAccessPanel()) {
                TeamAuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'actor_user_id' => $user->id,
                    'action' => 'auth.login',
                    'metadata' => [
                        'method' => 'POST',
                        'path' => '/login',
                    ],
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ]);
            }
            if ($user->canAccessPanel()) {
                return redirect()->intended('/dashboard');
            }

            return redirect()->intended('/area-membros');
        }

        return back()->withErrors([
            'email' => 'Credenciais inválidas.',
        ])->onlyInput('email');
    }

    /**
     * Login apenas com e-mail (quando habilitado nas configurações globais).
     */
    public function loginWithoutPassword(Request $request): RedirectResponse
    {
        if (DockerSetupState::isDocker() && ! DockerSetupState::isSetupDone()) {
            return redirect('/docker-setup');
        }

        $tenantId = SchoolLoginSettings::resolveTenantId($request);
        if ($tenantId === null) {
            abort(503);
        }

        $enabled = Setting::get('login_without_password', '0', $tenantId);
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            return back()->withErrors(['email' => 'Login apenas com e-mail não está habilitado.'])->onlyInput('email');
        }

        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->input('email'))->first();
        if (! $user || $user->canAccessPanel()) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        if (! $user->isAluno()) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }
        if ((int) $user->tenant_id !== $tenantId) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/area-membros');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->tenant_id && $user->canAccessPanel()) {
            TeamAuditLog::create([
                'tenant_id' => $user->tenant_id,
                'actor_user_id' => $user->id,
                'action' => 'auth.logout',
                'metadata' => [
                    'method' => 'POST',
                    'path' => '/logout',
                ],
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $to = $request->query('redirect');
        if (is_string($to) && $this->isSafeLoginRedirect($to)) {
            return redirect($to);
        }

        return redirect('/');
    }

    /**
     * Evita open redirect: só paths internos de login.
     */
    private function isSafeLoginRedirect(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }
        if (str_contains($path, '..')) {
            return false;
        }

        return $path === '/login';
    }
}
