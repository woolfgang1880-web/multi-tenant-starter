<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Services\Auth\AuthSessionService;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use Illuminate\Http\JsonResponse;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, AuthSessionService $auth): JsonResponse
    {
        $validated = $request->validated();
        $tenantCodigo = isset($validated['tenant_codigo']) ? trim((string) $validated['tenant_codigo']) : '';

        if ($tenantCodigo !== '') {
            $result = $auth->login(
                $tenantCodigo,
                $validated['usuario'],
                $validated['password'],
                $request
            );
        } else {
            $result = $auth->loginGlobal(
                $validated['usuario'],
                $validated['password'],
                $request
            );
        }

        if (! ($result['ok'] ?? false)) {
            if (($result['reason'] ?? '') === 'inactive') {
                return ApiResponse::make(
                    AuthErrorCode::ACCOUNT_INACTIVE,
                    'La cuenta está desactivada.',
                    null,
                    403
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
                'Credenciales incorrectas.',
                null,
                401
            );
        }

        if (! empty($result['needs_selection'])) {
            return ApiResponse::make(
                AuthErrorCode::TENANT_SELECTION_REQUIRED,
                'Seleccione empresa para continuar.',
                [
                    'selection_token' => $result['selection_token'],
                    'expires_in' => $result['expires_in'],
                    'tenants' => $result['tenants'],
                ],
                200
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
