<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenant\InactivateTenantCompanyRequest;
use App\Services\Tenant\TenantCompanyOperationalService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PlatformTenantInactivateController extends Controller
{
    public function __invoke(
        InactivateTenantCompanyRequest $request,
        TenantCompanyOperationalService $svc,
        string $tenant_codigo,
    ): JsonResponse {
        $tenant = $svc->findTenantByCodigoOrFail($tenant_codigo);
        $tenant = $svc->inactivate($tenant, $this->actorId($request));

        return ApiResponse::make('OK', 'Empresa inactivada operativamente.', $svc->formatTenantCompany($tenant));
    }
}
