<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenant\UpdateTenantCompanyRequest;
use App\Services\Tenant\TenantCompanyOperationalService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PlatformTenantUpdateController extends Controller
{
    public function __invoke(
        UpdateTenantCompanyRequest $request,
        TenantCompanyOperationalService $svc,
        string $tenant_codigo,
    ): JsonResponse {
        $tenant = $svc->findTenantByCodigoOrFail($tenant_codigo);
        $tenant = $svc->update($tenant, $request->validated(), $this->actorId($request));

        return ApiResponse::make('OK', 'Empresa actualizada.', $svc->formatTenantCompany($tenant));
    }
}
