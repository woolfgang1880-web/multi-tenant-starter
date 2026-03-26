<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\ListUsersRequest;
use App\Http\Requests\Api\V1\Users\StoreUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Services\Users\UserService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UserController extends Controller
{
    public function index(ListUsersRequest $request, UserService $users): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', 15);
        $paginator = $users->paginateForTenant($this->tenantId(), $perPage);

        return ApiResponse::make('OK', 'Listado de usuarios.', $users->formatPaginator($paginator));
    }

    public function store(StoreUserRequest $request, UserService $users): JsonResponse
    {
        $user = $users->create(
            $this->tenantId(),
            $request->validated(),
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Usuario creado.', $users->formatUser($user), 201);
    }

    public function show(UserService $users, int $id): JsonResponse
    {
        $user = $users->findForTenantOrFail($this->tenantId(), $id);

        return ApiResponse::make('OK', 'Usuario.', $users->formatUser($user));
    }

    public function update(UpdateUserRequest $request, UserService $users, int $id): JsonResponse
    {
        $user = $users->update(
            $this->tenantId(),
            $id,
            $request->validated(),
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Usuario actualizado.', $users->formatUser($user));
    }
}
