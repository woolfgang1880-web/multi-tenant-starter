<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Usuarios demo (idempotente).
     *
     * - Tenants: `firstOrCreate` por `codigo` (permite ejecutar solo este seeder sin `TenantSeeder`).
     * - Usuarios: `firstOrCreate` por `usuario` (único global); la contraseña solo se define al crear la fila.
     * - Roles: `syncWithoutDetaching` para no duplicar asignaciones en `user_roles`.
     * - Trial de PRUEBA1: ver `TenantSeeder`.
     */
    public function run(): void
    {
        $default = $this->ensureTenant('DEFAULT', 'Empresa principal', 'principal');
        $prueba1 = $this->ensureTenant('PRUEBA1', 'PRUEBA1', 'prueba1');
        $this->ensurePrueba1TrialDemo($prueba1);
        $pruebas = $this->ensureTenant('PRUEBAS', 'PRUEBAS', 'pruebas');

        $adminRole = fn (Tenant $t): Role => $this->ensureRole($t, 'admin', 'Administrador', 'Administración operativa');
        $userRole = fn (Tenant $t): Role => $this->ensureRole($t, 'user', 'Usuario', 'Usuario estándar');

        $this->ensureUser($default, 'admin_demo', 'DEMO-ADMIN', 'Admin123!', [$adminRole($default)->id], true);
        $this->ensureUser($default, 'user_demo', 'DEMO-USER', 'User123!', [$userRole($default)->id]);
        $this->ensureUser($default, 'manager_demo', 'DEMO-MANAGER', 'Manager123!', [$adminRole($default)->id]);

        $this->ensureUser($prueba1, 'admin_prueba1', 'P1-ADMIN', 'AdminPrueba1!', [$adminRole($prueba1)->id]);
        $this->ensureUser($prueba1, 'user_prueba1', 'P1-USER', 'UserPrueba1!', [$userRole($prueba1)->id]);

        $this->ensureUser($default, 'multi_demo', 'MULTI-001', 'MultiDemo123!', [$adminRole($default)->id]);
        $multi = User::query()->where('usuario', 'multi_demo')->first();
        if ($multi !== null) {
            $multi->tenants()->syncWithoutDetaching([$default->id, $prueba1->id]);
            $multi->roles()->syncWithoutDetaching([
                $adminRole($default)->id,
                $userRole($prueba1)->id,
            ]);
        }

        $this->ensureUser($pruebas, 'admin_pruebas', 'PRB-ADMIN', 'AdminPruebas123!', [$adminRole($pruebas)->id]);
        $this->ensureUser($pruebas, 'user_pruebas1', 'PRB-001', 'UserPruebas123!', [$userRole($pruebas)->id]);
        $this->ensureUser($pruebas, 'user_pruebas2', 'PRB-002', 'UserPruebas123!', [$userRole($pruebas)->id]);
    }

    /**
     * Alineado con `TenantSeeder`: trial demo solo si el tenant es nuevo o aún no tiene periodo de prueba.
     */
    private function ensurePrueba1TrialDemo(Tenant $tenant): void
    {
        if ($tenant->codigo !== 'PRUEBA1') {
            return;
        }

        if ($tenant->wasRecentlyCreated || $tenant->trial_starts_at === null) {
            $tenant->forceFill([
                'trial_starts_at' => $tenant->trial_starts_at ?? now()->subDay(),
                'trial_ends_at' => $tenant->trial_ends_at ?? now()->addDays(30),
                'subscription_status' => $tenant->subscription_status ?? Tenant::SUBSCRIPTION_TRIAL,
            ])->save();
        }
    }

    private function ensureTenant(string $codigo, string $nombre, string $slug): Tenant
    {
        return Tenant::query()->firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre,
                'slug' => $slug,
                'activo' => true,
            ]
        );
    }

    private function ensureRole(Tenant $tenant, string $slug, string $nombre, string $descripcion): Role
    {
        return Role::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => $slug],
            ['nombre' => $nombre, 'descripcion' => $descripcion]
        );
    }

    /**
     * @param  list<int>  $roleIds
     */
    private function ensureUser(
        Tenant $tenant,
        string $usuario,
        string $codigoCliente,
        string $plainPassword,
        array $roleIds,
        bool $isPlatformAdmin = false,
    ): void
    {
        $user = User::query()->firstOrCreate(
            ['usuario' => $usuario],
            [
                'tenant_id' => $tenant->id,
                'codigo_cliente' => $codigoCliente,
                'password_hash' => $plainPassword,
                'activo' => true,
                'fecha_alta' => now()->toDateString(),
                'is_platform_admin' => $isPlatformAdmin,
            ]
        );

        if ($isPlatformAdmin && ! $user->is_platform_admin) {
            $user->forceFill(['is_platform_admin' => true])->save();
        }

        $user->tenants()->syncWithoutDetaching([$tenant->id]);
        $user->roles()->syncWithoutDetaching($roleIds);

        if (config('demo.reset_demo_passwords_on_seed')) {
            $user->password_hash = $plainPassword;
            $user->save();
        }
    }
}
