<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\DeactivateUserRequest;
use App\Services\Users\UserService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class DeactivateUserController extends Controller
{
    public function __invoke(DeactivateUserRequest $request, UserService $users, int $id): JsonResponse
    {
        $user = $users->deactivate(
            $this->tenantId(),
            $id,
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Usuario inactivado.', $users->formatUser($user));
    }
}
