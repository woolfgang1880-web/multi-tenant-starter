<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\CreateTenantRequest;
use App\Services\Platform\PlatformTenantProvisioningService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PlatformTenantCreateController extends Controller
{
    public function __invoke(CreateTenantRequest $request, PlatformTenantProvisioningService $svc): JsonResponse
    {
        $actor = $this->actorId($request);
        $tenant = $svc->createTenant($request->validated(), $actor);

        return ApiResponse::make('OK', 'Tenant creado.', [
            'id' => $tenant->id,
            'codigo' => $tenant->codigo,
            'nombre' => $tenant->nombre,
            'slug' => $tenant->slug,
            'activo' => $tenant->activo,
        ], 201);
    }
}

