<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __invoke(Request $request, AuthSessionService $auth): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['tenant']);

        $activeTenantId = current_tenant_id() ?? $user->tenant_id;
        $roleTenantId = $activeTenantId ?? $user->tenant_id;

        $roles = $user->roles()
            ->where('roles.tenant_id', $roleTenantId)
            ->get(['id', 'nombre', 'slug', 'tenant_id']);

        $activeTenant = $user->tenantForActiveContext($activeTenantId);

        $accessibleTenants = $auth->accessibleActiveTenants($user)->map(fn ($t) => [
            'id' => $t->id,
            'codigo' => $t->codigo,
            'nombre' => $t->nombre,
            'slug' => $t->slug,
        ])->values()->all();

        return ApiResponse::make(
            'OK',
            'Perfil actual.',
            [
                'user' => [
                    'id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'codigo_cliente' => $user->codigo_cliente,
                    'usuario' => $user->usuario,
                    'activo' => $user->activo,
                    'is_platform_admin' => (bool) $user->is_platform_admin,
                    'fecha_alta' => $user->fecha_alta?->toDateString(),
                    'roles' => $roles->map(fn ($r) => [
                        'id' => $r->id,
                        'nombre' => $r->nombre,
                        'slug' => $r->slug,
                    ])->values()->all(),
                ],
                'tenant' => $activeTenant ? [
                    'id' => $activeTenant->id,
                    'codigo' => $activeTenant->codigo,
                    'nombre' => $activeTenant->nombre,
                    'slug' => $activeTenant->slug,
                    'subscription_status' => $activeTenant->subscription_status,
                    'trial_starts_at' => $activeTenant->trial_starts_at?->toISOString(),
                    'trial_ends_at' => $activeTenant->trial_ends_at?->toISOString(),
                ] : null,
                'accessible_tenants' => $accessibleTenants,
            ],
            200
        );
    }
}