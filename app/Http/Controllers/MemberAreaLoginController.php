<?php

namespace App\Http\Controllers;

use App\Models\MemberActivityLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberAreaLoginController extends Controller
{
    /**
     * Best-effort activity log for proof/compliance. Must never block login.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function logMemberActivity(Request $request, Product $product, User $user, string $event, array $metadata = []): void
    {
        try {
            MemberActivityLog::create([
                'tenant_id' => $product->tenant_id ?? $user->tenant_id,
                'user_id' => $user->id,
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

    public function magicAccess(Request $request, string $slug): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $slug = $request->route('slug') ?? $request->attributes->get('member_area_slug') ?? $slug;
        $userId = (int) $request->query('u', 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (! $user || ! $product->hasMemberAreaAccess($user)) {
            return redirect()->route('login')->with('error', 'Link inválido ou expirado.');
        }
        Auth::login($user);
        $request->session()->regenerate();

        $this->logMemberActivity($request, $product, $user, 'member_area.magic_access', [
            'mode' => 'path',
            'path' => '/'.ltrim($request->path(), '/'),
        ]);

        return redirect()->intended(route('member-area-app.show', ['slug' => $slug]));
    }

    public function magicAccessHost(Request $request): RedirectResponse
    {
        $product = $request->route('product') ?? $request->attributes->get('member_area_product');
        if (! $product instanceof Product || $product->type !== Product::TYPE_AREA_MEMBROS) {
            abort(404, 'Área de membros não encontrada.');
        }
        $userId = (int) $request->query('u', 0);
        $user = $userId > 0 ? User::find($userId) : null;
        if (! $user || ! $product->hasMemberAreaAccess($user)) {
            return redirect()->to('/login')->with('error', 'Link inválido ou expirado.');
        }
        Auth::login($user);
        $request->session()->regenerate();

        $this->logMemberActivity($request, $product, $user, 'member_area.magic_access', [
            'mode' => 'host',
            'path' => '/'.ltrim($request->path(), '/'),
        ]);

        return redirect()->to('/');
    }
}
