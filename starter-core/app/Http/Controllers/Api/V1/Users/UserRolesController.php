<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\AttachUserRolesRequest;
use App\Http\Requests\Api\V1\Users\SyncUserRolesRequest;
use App\Services\Users\UserRoleAssignmentService;
use App\Services\Users\UserService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UserRolesController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function sync(SyncUserRolesRequest $request, UserRoleAssignmentService $assignment, int $id): JsonResponse
    {
        $user = $assignment->sync(
            $this->tenantId(),
            $id,
            $request->validated('role_ids'),
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Roles sincronizados.', $this->users->formatUser($user));
    }

    public function attach(AttachUserRolesRequest $request, UserRoleAssignmentService $assignment, int $id): JsonResponse
    {
        $user = $assignment->attach(
            $this->tenantId(),
            $id,
            $request->validated('role_ids'),
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Roles añadidos.', $this->users->formatUser($user));
    }
}
