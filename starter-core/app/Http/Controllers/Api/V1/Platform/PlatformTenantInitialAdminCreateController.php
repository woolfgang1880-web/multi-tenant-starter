<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\CreateTenantAdminRequest;
use App\Services\Platform\PlatformTenantProvisioningService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PlatformTenantInitialAdminCreateController extends Controller
{
    public function __invoke(
        CreateTenantAdminRequest $request,
        PlatformTenantProvisioningService $svc,
        string $tenant_codigo,
    ): JsonResponse {
        $actor = $this->actorId($request);
        $user = $svc->createTenantInitialAdmin($tenant_codigo, $request->validated(), $actor);

        return ApiResponse::make('OK', 'Admin inicial creado.', [
            'id' => $user->id,
            'usuario' => $user->usuario,
            'tenant_id' => $user->tenant_id,
            'roles' => $user->roles->map(fn ($r) => [
                'id' => $r->id,
                'tenant_id' => $r->tenant_id,
                'slug' => $r->slug,
                'nombre' => $r->nombre,
            ]),
        ], 201);
    }
}

