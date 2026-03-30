<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenant\ReactivateTenantCompanyRequest;
use App\Services\Tenant\TenantCompanyOperationalService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TenantCompanyReactivateController extends Controller
{
    public function __invoke(
        ReactivateTenantCompanyRequest $request,
        TenantCompanyOperationalService $svc,
    ): JsonResponse {
        $tenant = $svc->findTenantByIdOrFail($this->tenantId());
        $tenant = $svc->reactivate($tenant, $this->actorId($request));

        return ApiResponse::make('OK', 'Empresa reactivada operativamente.', $svc->formatTenantCompany($tenant));
    }
}
