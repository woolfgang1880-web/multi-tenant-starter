<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use App\Support\Logging\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida access token Sanctum + fila coherente en `user_sessions` (activa, no expirada).
 * Debe ir después de `auth:sanctum` y, si aplica, `tenant.context`.
 */
final class EnsureActiveApiSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::make(
                AuthErrorCode::UNAUTHENTICATED,
                'No autenticado.',
                null,
                401
            );
        }

        $token = $user->currentAccessToken();

        if ($token instanceof TransientToken) {
            SecurityLogger::accessDenied('transient_token_not_supported', $user->id, AuthErrorCode::SESSION_INVALID);

            return ApiResponse::make(
                AuthErrorCode::SESSION_INVALID,
                'Tipo de token no soportado para esta API.',
                null,
                401
            );
        }

        if (! $token instanceof PersonalAccessToken) {
            SecurityLogger::accessDenied('missing_personal_access_token', $user->id, AuthErrorCode::SESSION_INVALID);

            return ApiResponse::make(
                AuthErrorCode::SESSION_INVALID,
                'Sesión no válida.',
                null,
                401
            );
        }

        $sessionUuid = $token->name;

        $session = UserSession::query()
            ->where('session_uuid', $sessionUuid)
            ->where('user_id', $user->id)
            ->first();

        if ($session === null) {
            SecurityLogger::accessDenied('session_row_missing', $user->id, AuthErrorCode::SESSION_INVALID);

            return ApiResponse::make(
                AuthErrorCode::SESSION_INVALID,
                'Sesión no válida.',
                null,
                401
            );
        }

        if (! $session->is_active && $session->invalidation_reason === 'superseded_login') {
            SecurityLogger::sessionSuperseded($user->id, $sessionUuid);

            return ApiResponse::make(
                AuthErrorCode::SESSION_SUPERSEDED,
                'La sesión fue reemplazada por un nuevo inicio de sesión.',
                null,
                401
            );
        }

        if ($session->invalidated_at !== null) {
            return ApiResponse::make(
                AuthErrorCode::SESSION_INVALID,
                'Sesión invalidada.',
                null,
                401
            );
        }

        if (! $session->is_active) {
            SecurityLogger::accessDenied('session_inactive', $user->id, AuthErrorCode::SESSION_INVALID);

            return ApiResponse::make(
                AuthErrorCode::SESSION_INVALID,
                'Sesión no válida.',
                null,
                401
            );
        }

        if ($session->expires_at !== null && $session->expires_at->isPast()) {
            return ApiResponse::make(
                AuthErrorCode::SESSION_EXPIRED,
                'La sesión ha expirado.',
                null,
                401
            );
        }

        $session->forceFill(['last_seen_at' => now()])->save();

        return $next($request);
    }
}
