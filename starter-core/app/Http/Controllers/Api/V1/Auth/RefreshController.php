<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RefreshRequest;
use App\Services\Auth\AuthSessionService;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use Illuminate\Http\JsonResponse;

final class RefreshController extends Controller
{
    public function __invoke(RefreshRequest $request, AuthSessionService $auth): JsonResponse
    {
        $validated = $request->validated();

        $result = $auth->refresh($validated['refresh_token'], $request);

        if (! $result['ok']) {
            $reason = $result['reason'] ?? 'invalid';

            if ($reason === 'expired') {
                return ApiResponse::make(
                    AuthErrorCode::REFRESH_EXPIRED,
                    'El token de renovación ha expirado.',
                    null,
                    401
                );
            }

            if ($reason === 'session_inactive') {
                return ApiResponse::make(
                    AuthErrorCode::SESSION_INVALID,
                    'La sesión ya no es válida. Vuelva a iniciar sesión.',
                    null,
                    401
                );
            }

            return ApiResponse::make(
                AuthErrorCode::REFRESH_INVALID,
                'Token de renovación inválido o revocado.',
                null,
                401
            );
        }

        return ApiResponse::make(
            'OK',
            'Token renovado.',
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
