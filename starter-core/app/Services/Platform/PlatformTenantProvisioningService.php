<?php

namespace App\Services\Platform;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PlatformTenantProvisioningService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createTenant(array $data, int $actorUserId): Tenant
    {
        return DB::transaction(function () use ($data, $actorUserId) {
            $slug = $this->slugFromCodigo((string) $data['codigo']);

            $tenant = Tenant::query()->create([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'slug' => $slug,
                'activo' => $data['activo'] ?? true,
            ]);

            Log::channel('security')->info('platform.tenant.created', [
                'actor_user_id' => $actorUserId,
                'tenant_id' => $tenant->id,
                'tenant_codigo' => $tenant->codigo,
                'tenant_slug' => $tenant->slug,
            ]);

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTenantInitialAdmin(string $tenantCodigo, array $data, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantCodigo, $data, $actorUserId) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->where('codigo', $tenantCodigo)->firstOrFail();

            $roles = $this->ensureBaseRolesForTenant($tenant, $actorUserId);
            $adminRole = $roles['admin'];

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'codigo_cliente' => $data['admin_codigo_cliente'] ?? null,
                'usuario' => $data['admin_usuario'],
                'password_hash' => $data['admin_password'],
                'activo' => true,
                'fecha_alta' => now()->toDateString(),
                // El admin inicial es admin de tenant, NO super admin de plataforma.
                'is_platform_admin' => false,
            ]);

            // Para asegurar comportamiento consistente con pertenencia N:N.
            $user->tenants()->syncWithoutDetaching([$tenant->id]);
            $user->roles()->syncWithoutDetaching([$adminRole->id]);

            AdminAuditLogger::userCreated($actorUserId, $user->id, $tenant->id);
            AdminAuditLogger::userRolesAttached($actorUserId, $user->id, $tenant->id, 1);

            return $user->fresh(['roles']);
        });
    }

    /**
     * @return array{super_admin: Role, admin: Role, user: Role}
     */
    private function ensureBaseRolesForTenant(Tenant $tenant, int $actorUserId): array
    {
        $templates = [
            ['slug' => 'super_admin', 'nombre' => 'Super administrador', 'descripcion' => 'Acceso total del tenant'],
            ['slug' => 'admin', 'nombre' => 'Administrador', 'descripcion' => 'Administración operativa'],
            ['slug' => 'user', 'nombre' => 'Usuario', 'descripcion' => 'Usuario estándar'],
        ];

        $out = [];

        foreach ($templates as $tpl) {
            $role = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $tpl['slug'])
                ->first();

            if ($role === null) {
                $role = Role::query()->create([
                    'tenant_id' => $tenant->id,
                    'slug' => $tpl['slug'],
                    'nombre' => $tpl['nombre'],
                    'descripcion' => $tpl['descripcion'],
                ]);

                AdminAuditLogger::roleCreated($actorUserId, $role->id, $tenant->id);
            }

            $out[$tpl['slug']] = $role;
        }

        return $out;
    }

    private function slugFromCodigo(string $codigo): string
    {
        $slug = Str::slug($codigo);
        if ($slug === '') {
            $slug = strtolower(Str::random(10));
        }

        return $slug;
    }
}

