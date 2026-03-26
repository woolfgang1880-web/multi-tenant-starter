<?php

namespace App\Http\Controllers\Api\V1\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Roles\ListRolesRequest;
use App\Http\Requests\Api\V1\Roles\StoreRoleRequest;
use App\Http\Requests\Api\V1\Roles\UpdateRoleRequest;
use App\Services\Roles\RoleService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RoleController extends Controller
{
    public function index(ListRolesRequest $request, RoleService $roles): JsonResponse
    {
        $perPage = (int) $request->validated('per_page', 50);
        $paginator = $roles->paginateForTenant($this->tenantId(), $perPage);

        return ApiResponse::make('OK', 'Listado de roles.', $roles->formatPaginator($paginator));
    }

    public function store(StoreRoleRequest $request, RoleService $roles): JsonResponse
    {
        $role = $roles->create(
            $this->tenantId(),
            $request->validated(),
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Rol creado.', $roles->formatRole($role), 201);
    }

    public function show(RoleService $roles, int $id): JsonResponse
    {
        $role = $roles->findForTenantOrFail($this->tenantId(), $id);

        return ApiResponse::make('OK', 'Rol.', $roles->formatRole($role));
    }

    public function update(UpdateRoleRequest $request, RoleService $roles, int $id): JsonResponse
    {
        $role = $roles->update(
            $this->tenantId(),
            $id,
            $request->validated(),
            $this->actorId($request)
        );

        return ApiResponse::make('OK', 'Rol actualizado.', $roles->formatRole($role));
    }
}
