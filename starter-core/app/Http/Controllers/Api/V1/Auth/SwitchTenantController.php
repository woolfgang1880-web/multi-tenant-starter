<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SwitchTenantRequest;
use App\Services\Auth\AuthSessionService;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

final class SwitchTenantController extends Controller
{
    public function __invoke(SwitchTenantRequest $request, AuthSessionService $auth): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return ApiResponse::make(
                AuthErrorCode::SESSION_INVALID,
                'Sesión no válida.',
                null,
                401
            );
        }

        $sessionUuid = $token->name;
        $tenantCodigo = $request->validated()['tenant_codigo'];

        $result = $auth->switchSessionTenant($user, $sessionUuid, $tenantCodigo, $request);

        if (! ($result['ok'] ?? false)) {
            return match ($result['reason'] ?? '') {
                'tenant_not_found' => ApiResponse::make(
                    AuthErrorCode::TENANT_NOT_FOUND,
                    'Empresa no encontrada o inactiva.',
                    null,
                    404
                ),
                'forbidden' => ApiResponse::make(
                    AuthErrorCode::FORBIDDEN,
                    'No tienes acceso a esa empresa.',
                    null,
                    403
                ),
                'subscription_blocked' => ApiResponse::make(
                    AuthErrorCode::SUBSCRIPTION_EXPIRED,
                    'El periodo de prueba de esta empresa ha finalizado o el acceso no está disponible.',
                    null,
                    403
                ),
                default => ApiResponse::make(
                    AuthErrorCode::SESSION_INVALID,
                    'Sesión no válida.',
                    null,
                    401
                ),
            };
        }

        return ApiResponse::make(
            'OK',
            'Empresa activa actualizada.',
            [
                'tenant' => $result['tenant'],
            ],
            200
        );
    }
}
