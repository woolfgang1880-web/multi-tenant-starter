<?php

namespace App\Observers;

use App\Models\User;

/**
 * Mantiene user_tenants alineado con users.tenant_id (tenant “principal” / hogar).
 */
final class UserObserver
{
    public function created(User $user): void
    {
        $user->tenants()->syncWithoutDetaching([$user->tenant_id]);
    }
}
