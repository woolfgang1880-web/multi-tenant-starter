<?php

namespace App\Support\Tenancy\Resolvers;

use App\Contracts\Tenancy\TenantResolver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tenant activo = el de la sesión API (`user_sessions.tenant_id`), fijado en el login.
 * Sin token Sanctum o sin fila de sesión, cae al tenant principal del usuario (`users.tenant_id`).
 */
final class AuthenticatedUserTenantResolver implements TenantResolver
{
    public function resolve(?Request $request = null): ?Tenant
    {
        $request ??= request();

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $session = UserSession::query()
                ->where('session_uuid', $token->name)
                ->where('user_id', $user->id)
                ->first();

            if ($session !== null && $session->tenant_id !== null) {
                return Tenant::query()->find($session->tenant_id);
            }
        }

        return $user->tenant;
    }
}
