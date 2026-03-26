<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutController extends Controller
{
    public function __invoke(Request $request, AuthSessionService $auth): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken) {
            return ApiResponse::make(
                AuthErrorCode::UNAUTHENTICATED,
                'No autenticado.',
                null,
                401
            );
        }

        $auth->logout($user, $token);

        return ApiResponse::make(
            'OK',
            'Sesión cerrada.',
            null,
            200
        );
    }
}
