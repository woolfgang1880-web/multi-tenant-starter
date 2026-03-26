<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\Auth\UserAccessRevoker;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UserService
{
    public function __construct(
        private readonly UserAccessRevoker $accessRevoker,
    ) {}

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $tenantId));
            })
            ->with(['roles:id,nombre,slug,tenant_id'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForTenantOrFail(int $tenantId, int $userId): User
    {
        return User::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $tenantId));
            })
            ->with(['roles:id,nombre,slug,tenant_id'])
            ->whereKey($userId)
            ->firstOrFail();
    }

    public function create(int $tenantId, array $data, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantId, $data, $actorUserId) {
            $user = User::query()->create([
                'tenant_id' => $tenantId,
                'codigo_cliente' => $data['codigo_cliente'] ?? null,
                'usuario' => $data['usuario'],
                'password_hash' => $data['password'],
                'activo' => $data['activo'] ?? true,
                'fecha_alta' => $data['fecha_alta'] ?? now()->toDateString(),
            ]);

            AdminAuditLogger::userCreated($actorUserId, $user->id, $tenantId);

            return $user->load(['roles:id,nombre,slug,tenant_id']);
        });
    }

    public function update(int $tenantId, int $userId, array $data, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantId, $userId, $data, $actorUserId) {
            $user = $this->findForTenantOrFail($tenantId, $userId);

            $payload = Arr::only($data, ['usuario', 'codigo_cliente', 'fecha_alta', 'activo']);

            if (array_key_exists('password', $data) && $data['password'] !== null) {
                $payload['password_hash'] = $data['password'];
            }

            if ($payload !== []) {
                $user->fill($payload);
                $user->save();
            }

            AdminAuditLogger::userUpdated($actorUserId, $user->id, $tenantId);

            return $user->fresh(['roles:id,nombre,slug,tenant_id']);
        });
    }

    public function deactivate(int $tenantId, int $userId, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantId, $userId, $actorUserId) {
            $user = $this->findForTenantOrFail($tenantId, $userId);

            $user->forceFill(['activo' => false])->save();

            $this->accessRevoker->revokeAll($user);

            AdminAuditLogger::userDeactivated($actorUserId, $user->id, $tenantId);

            return $user->fresh(['roles:id,nombre,slug,tenant_id']);
        });
    }

    public function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'codigo_cliente' => $user->codigo_cliente,
            'usuario' => $user->usuario,
            'activo' => $user->activo,
            'fecha_alta' => $user->fecha_alta?->toDateString(),
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'slug' => $r->slug,
                ])->values()->all()
                : [],
        ];
    }

    public function formatPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (User $u) => $this->formatUser($u))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
