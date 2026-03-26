<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserRoleAssignmentService
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function sync(int $tenantId, int $userId, array $roleIds, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantId, $userId, $roleIds, $actorUserId) {
            $user = $this->userService->findForTenantOrFail($tenantId, $userId);

            $this->assertRolesBelongToTenant($tenantId, $roleIds);

            $roleIds = array_values(array_unique($roleIds));
            $keepOtherTenants = $user->roles()
                ->where('roles.tenant_id', '!=', $tenantId)
                ->pluck('roles.id')
                ->all();

            $user->roles()->sync(array_values(array_unique(array_merge($keepOtherTenants, $roleIds))));

            AdminAuditLogger::userRolesSynced($actorUserId, $user->id, $tenantId, count(array_unique($roleIds)));

            return $user->fresh(['roles:id,nombre,slug,tenant_id']);
        });
    }

    public function attach(int $tenantId, int $userId, array $roleIds, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantId, $userId, $roleIds, $actorUserId) {
            $user = $this->userService->findForTenantOrFail($tenantId, $userId);

            $unique = array_values(array_unique($roleIds));

            $this->assertRolesBelongToTenant($tenantId, $unique);

            $existing = $user->roles()->pluck('roles.id')->all();

            $toAttach = array_values(array_diff($unique, $existing));

            if ($toAttach !== []) {
                $user->roles()->attach($toAttach);
            }

            AdminAuditLogger::userRolesAttached($actorUserId, $user->id, $tenantId, count($toAttach));

            return $user->fresh(['roles:id,nombre,slug,tenant_id']);
        });
    }

    /**
     * Defensa en profundidad: las FormRequests (Attach/SyncUserRolesRequest) ya restringen
     * `role_ids` al tenant vía Rule::exists(..., 'tenant_id', current_tenant_id()). Esta
     * comprobación se mantiene a propósito en el servicio para que cualquier otra vía futura
     * que llame a sync/attach no pueda omitir el aislamiento por tenant.
     */
    private function assertRolesBelongToTenant(int $tenantId, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        $count = Role::query()
            ->forTenant($tenantId)
            ->whereIn('id', $roleIds)
            ->count();

        if ($count !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => ['Uno o más roles no existen en este tenant.'],
            ]);
        }
    }
}
