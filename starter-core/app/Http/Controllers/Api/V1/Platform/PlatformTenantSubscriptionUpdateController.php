<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformTenantProvisioningService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformTenantSubscriptionUpdateController extends Controller
{
    public function __invoke(Request $request, PlatformTenantProvisioningService $svc, string $tenant_codigo): JsonResponse
    {
        $validated = $request->validate([
            'subscription_status' => [
                'required',
                'string',
                Rule::in(['active', 'suspended']),
            ],
        ]);

        $tenant = $svc->updateSubscriptionTrialTransition(
            $tenant_codigo,
            $validated['subscription_status'],
            $request->user()?->getAuthIdentifier()
        );

        return ApiResponse::make('OK', 'Estado de suscripción actualizado.', [
            'codigo' => $tenant->codigo,
            'subscription_status' => $tenant->subscription_status,
        ]);
    }
}
