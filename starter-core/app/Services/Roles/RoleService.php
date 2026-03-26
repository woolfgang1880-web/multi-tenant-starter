<?php

namespace App\Services\Roles;

use App\Models\Role;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class RoleService
{
    public function paginateForTenant(int $tenantId, int $perPage = 50): LengthAwarePaginator
    {
        return Role::query()
            ->forTenant($tenantId)
            ->orderBy('nombre')
            ->paginate($perPage);
    }

    public function findForTenantOrFail(int $tenantId, int $roleId): Role
    {
        return Role::query()
            ->forTenant($tenantId)
            ->whereKey($roleId)
            ->firstOrFail();
    }

    public function create(int $tenantId, array $data, int $actorUserId): Role
    {
        return DB::transaction(function () use ($tenantId, $data, $actorUserId) {
            $role = Role::query()->create([
                'tenant_id' => $tenantId,
                'nombre' => $data['nombre'],
                'slug' => $data['slug'],
                'descripcion' => $data['descripcion'] ?? null,
            ]);

            AdminAuditLogger::roleCreated($actorUserId, $role->id, $tenantId);

            return $role;
        });
    }

    public function update(int $tenantId, int $roleId, array $data, int $actorUserId): Role
    {
        return DB::transaction(function () use ($tenantId, $roleId, $data, $actorUserId) {
            $role = $this->findForTenantOrFail($tenantId, $roleId);

            $role->fill([
                'nombre' => $data['nombre'],
                'slug' => $data['slug'],
                'descripcion' => $data['descripcion'] ?? null,
            ]);
            $role->save();

            AdminAuditLogger::roleUpdated($actorUserId, $role->id, $tenantId);

            return $role->fresh();
        });
    }

    public function formatRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'tenant_id' => $role->tenant_id,
            'nombre' => $role->nombre,
            'slug' => $role->slug,
            'descripcion' => $role->descripcion,
        ];
    }

    public function formatPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (Role $r) => $this->formatRole($r))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
