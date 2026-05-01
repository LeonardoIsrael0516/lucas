<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;

final class StudentAreaTenant
{
    /**
     * Tenant do infoprodutor associado ao aluno (direto ou via primeiro produto área de membros).
     */
    public static function idForUser(User $user): ?int
    {
        $tenantId = $user->tenant_id;
        if ($tenantId) {
            return (int) $tenantId;
        }

        $ownedProducts = $user->products()
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $p) => $p->type === Product::TYPE_AREA_MEMBROS);

        $fromProduct = (int) ($ownedProducts->first()?->tenant_id ?? 0);

        return $fromProduct > 0 ? $fromProduct : null;
    }
}
