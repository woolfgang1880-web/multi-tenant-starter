<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformTenantProvisioningService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformTenantsIndexController extends Controller
{
    public function __invoke(Request $request, PlatformTenantProvisioningService $svc): JsonResponse
    {
        $payload = $svc->listTenantsForPlatform($request);

        return ApiResponse::make('OK', 'Listado de empresas.', $payload);
    }
}

