<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Roles base dinámicos por tenant (RBAC sin permisos granulares aún).
     * Idempotente: firstOrCreate por (tenant_id, slug).
     * Tenants cubiertos: DEFAULT, PRUEBA1, PRUEBAS (deben existir vía `TenantSeeder`).
     */
    public function run(): void
    {
        $roleTemplates = [
            ['nombre' => 'Super administrador', 'slug' => 'super_admin', 'descripcion' => 'Acceso total del tenant'],
            ['nombre' => 'Administrador', 'slug' => 'admin', 'descripcion' => 'Administración operativa'],
            ['nombre' => 'Usuario', 'slug' => 'user', 'descripcion' => 'Usuario estándar'],
        ];

        $codigos = ['DEFAULT', 'PRUEBA1', 'PRUEBAS'];

        foreach ($codigos as $codigo) {
            $tenant = Tenant::query()->where('codigo', $codigo)->first();
            if ($tenant === null) {
                continue;
            }

            foreach ($roleTemplates as $role) {
                Role::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'slug' => $role['slug'],
                    ],
                    [
                        'nombre' => $role['nombre'],
                        'descripcion' => $role['descripcion'],
                    ]
                );
            }
        }
    }
}
