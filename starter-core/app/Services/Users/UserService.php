<?php

namespace App\Services\Users;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\UserAccessRevoker;
use App\Support\Api\ApiResponse;
use App\Support\Auth\AuthErrorCode;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UserService
{
    public function __construct(
        private readonly UserAccessRevoker $accessRevoker,
    ) {}

    /**
     * Misma definición de pertenencia que el listado de usuarios del tenant (paginateForTenant / findForTenantOrFail).
     */
    public function queryForTenantMembers(int $tenantId): Builder
    {
        return User::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($t) => $t->where('tenants.id', $tenantId));
            });
    }

    public function countMembersForTenant(int $tenantId): int
    {
        return $this->queryForTenantMembers($tenantId)->count();
    }

    /**
     * En estado comercial trial solo puede existir un usuario en total en la organización.
     */
    public function ensureCanCreateUserForTenant(int $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return;
        }

        if ($tenant->subscription_status !== Tenant::SUBSCRIPTION_TRIAL) {
            return;
        }

        if ($this->countMembersForTenant($tenantId) >= 1) {
            throw new HttpResponseException(
                ApiResponse::make(
                    AuthErrorCode::TRIAL_USER_LIMIT_REACHED,
                    'En periodo de prueba solo puede existir un usuario. Active un plan o contacte al administrador para agregar más usuarios.',
                    null,
                    403
                )
            );
        }
    }

    public function paginateForTenant(int $tenantId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->queryForTenantMembers($tenantId)
            ->with(['roles:id,nombre,slug,tenant_id'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForTenantOrFail(int $tenantId, int $userId): User
    {
        return $this->queryForTenantMembers($tenantId)
            ->with(['roles:id,nombre,slug,tenant_id'])
            ->whereKey($userId)
            ->firstOrFail();
    }

    public function create(int $tenantId, array $data, int $actorUserId): User
    {
        $this->ensureCanCreateUserForTenant($tenantId);

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
