<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductAccessService
{
    /**
     * Concede ou renova acesso do aluno ao produto.
     *
     * @param  bool  $renewAccessPeriod  Se true, recalcula access_expires_at a partir de agora (quando o produto tem duração em dias).
     */
    public function grant(User $user, Product $product, bool $renewAccessPeriod = true): void
    {
        $product->users()->syncWithoutDetaching([$user->id]);

        $days = $product->course_access_days;
        if ($days === null || (int) $days <= 0) {
            DB::table('product_user')
                ->where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->update(['access_expires_at' => null]);

            return;
        }

        if (! $renewAccessPeriod) {
            $existing = DB::table('product_user')
                ->where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->value('access_expires_at');
            if ($existing !== null) {
                return;
            }
        }

        $expiresAt = now()->addDays((int) $days)->endOfDay();

        DB::table('product_user')
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->update(['access_expires_at' => $expiresAt]);
    }

    public function accessExpiresAt(Product $product, User $user): ?Carbon
    {
        $raw = DB::table('product_user')
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->value('access_expires_at');

        return $raw ? Carbon::parse($raw)->endOfDay() : null;
    }

    public function hasActiveAccess(Product $product, User $user): bool
    {
        if (($user->isAdmin() || $user->isInfoprodutor()) && (int) $user->tenant_id === (int) $product->tenant_id) {
            return true;
        }

        if (! $product->users()->where('users.id', $user->id)->exists()) {
            return false;
        }

        $expires = $this->accessExpiresAt($product, $user);

        return $expires === null || $expires->isFuture();
    }
}
