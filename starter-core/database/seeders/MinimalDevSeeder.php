<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Perfil mínimo: 1 platform admin, 1 empresa emisor fiscal válida, 1 admin de empresa, roles base.
 * Uso: SEED_PROFILE=minimal o db:seed --class=MinimalDevSeeder
 */
class MinimalDevSeeder extends Seeder
{
    public function run(): void
    {
        $codigo = 'DEMO-EMISOR';
        $slug = Str::slug($codigo);
        if ($slug === '') {
            $slug = 'demo-emisor';
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => 'Empresa demo emisor',
                'slug' => $slug,
                'activo' => true,
                'trial_starts_at' => now(),
                'trial_ends_at' => now()->addDays(14),
                'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
                'origen_datos' => 'manual',
                'tipo_contribuyente' => 'persona_moral',
                'rfc' => 'CAN010101AA1',
                'nombre_fiscal' => 'Demo Emisor SA de CV',
                'regimen_fiscal_principal' => '601',
                'codigo_postal' => '06000',
                'estado' => 'CIUDAD DE MEXICO',
                'colonia' => 'CENTRO',
            ]
        );

        $templates = [
            ['slug' => 'super_admin', 'nombre' => 'Super administrador', 'descripcion' => 'Acceso total del tenant'],
            ['slug' => 'admin', 'nombre' => 'Administrador', 'descripcion' => 'Administración operativa'],
            ['slug' => 'user', 'nombre' => 'Usuario', 'descripcion' => 'Usuario estándar'],
        ];

        foreach ($templates as $tpl) {
            Role::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $tpl['slug']],
                ['nombre' => $tpl['nombre'], 'descripcion' => $tpl['descripcion']]
            );
        }

        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();

        $platformUser = User::query()->firstOrCreate(
            ['usuario' => 'platform_demo_min'],
            [
                'tenant_id' => $tenant->id,
                'codigo_cliente' => 'PLAT-MIN',
                'password_hash' => 'PlatformMin123!',
                'activo' => true,
                'fecha_alta' => now()->toDateString(),
                'is_platform_admin' => true,
            ]
        );
        $platformUser->tenants()->syncWithoutDetaching([$tenant->id]);
        if (! $platformUser->is_platform_admin) {
            $platformUser->forceFill(['is_platform_admin' => true])->save();
        }

        $tenantAdmin = User::query()->firstOrCreate(
            ['usuario' => 'admin_emisor_demo'],
            [
                'tenant_id' => $tenant->id,
                'codigo_cliente' => 'ADM-DEMO',
                'password_hash' => 'AdminEmisor123!',
                'activo' => true,
                'fecha_alta' => now()->toDateString(),
                'is_platform_admin' => false,
            ]
        );
        $tenantAdmin->tenants()->syncWithoutDetaching([$tenant->id]);
        $tenantAdmin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
