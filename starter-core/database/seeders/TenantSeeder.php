<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Tenants base multi-tenant (idempotente: firstOrCreate por codigo).
     * No actualiza nombre/slug/activo de filas ya existentes.
     * Excepción: PRUEBA1 puede recibir datos de trial demo si el tenant es nuevo o aún no tiene `trial_starts_at`.
     */
    public function run(): void
    {
        $tenants = [
            [
                'codigo' => 'DEFAULT',
                'nombre' => 'Empresa principal',
                'slug' => 'principal',
                'activo' => true,
            ],
            [
                'codigo' => 'PRUEBA1',
                'nombre' => 'PRUEBA1',
                'slug' => 'prueba1',
                'activo' => true,
            ],
            [
                'codigo' => 'PRUEBAS',
                'nombre' => 'PRUEBAS',
                'slug' => 'pruebas',
                'activo' => true,
            ],
        ];

        foreach ($tenants as $row) {
            $codigo = $row['codigo'];
            unset($row['codigo']);
            $tenant = Tenant::query()->firstOrCreate(
                ['codigo' => $codigo],
                $row
            );

            if ($codigo === 'PRUEBA1' && ($tenant->wasRecentlyCreated || $tenant->trial_starts_at === null)) {
                $tenant->forceFill([
                    'trial_starts_at' => $tenant->trial_starts_at ?? now()->subDay(),
                    'trial_ends_at' => $tenant->trial_ends_at ?? now()->addDays(30),
                    'subscription_status' => $tenant->subscription_status ?? Tenant::SUBSCRIPTION_TRIAL,
                ])->save();
            }
        }
    }
}
