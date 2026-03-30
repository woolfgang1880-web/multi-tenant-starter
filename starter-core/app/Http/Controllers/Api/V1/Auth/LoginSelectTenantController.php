<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginSelectTenantRequest;
use App\Services\Auth\AuthSessionService;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use Illuminate\Http\JsonResponse;

final class LoginSelectTenantController extends Controller
{
    public function __invoke(LoginSelectTenantRequest $request, AuthSessionService $auth): JsonResponse
    {
        $validated = $request->validated();

        $result = $auth->completeLoginSelection(
            $validated['selection_token'],
            $validated['tenant_codigo'],
            $request
        );

        if (! $result['ok']) {
            if (($result['reason'] ?? '') === 'selection_invalid') {
                return ApiResponse::make(
                    AuthErrorCode::SELECTION_TOKEN_INVALID,
                    'Token de selección inválido o expirado. Inicie sesión de nuevo.',
                    null,
                    401
                );
            }

            if (($result['reason'] ?? '') === 'subscription_blocked') {
                return ApiResponse::make(
                    AuthErrorCode::SUBSCRIPTION_EXPIRED,
                    'El periodo de prueba de esta empresa ha finalizado o el acceso no está disponible.',
                    null,
                    403
                );
            }

            return ApiResponse::make(
                AuthErrorCode::INVALID_CREDENTIALS,
                'Empresa no válida o sin acceso.',
                null,
                401
            );
        }

        return ApiResponse::make(
            'OK',
            'Inicio de sesión correcto.',
            [
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
                'session_uuid' => $result['session_uuid'],
            ],
            200
        );
    }
}
