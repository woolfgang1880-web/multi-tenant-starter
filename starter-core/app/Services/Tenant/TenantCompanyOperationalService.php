<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edición e inactivación/reactivación operativa de empresas (Tenant).
 *
 * Riesgo intencionalmente NO tocado: login/auth (`activo`, `subscription_status`, `allowsApiAccess()`)
 * siguen igual; el estado operativo es independiente salvo listados y reglas de negocio aquí.
 */
final class TenantCompanyOperationalService
{
    public function findTenantByCodigoOrFail(string $codigo): Tenant
    {
        return Tenant::query()->where('codigo', $codigo)->firstOrFail();
    }

    public function findTenantByIdOrFail(int $id): Tenant
    {
        return Tenant::query()->findOrFail($id);
    }

    /**
     * Transición masiva inactive → expired cuando ya pasaron 30 días.
     * Idempotente; apto para llamar desde listados o un job futuro.
     */
    public function syncExpiredOperationalStatuses(): int
    {
        return Tenant::query()
            ->where('operational_status', Tenant::OPERATIONAL_INACTIVE)
            ->whereNotNull('inactivated_at')
            ->where('inactivated_at', '<=', now()->subDays(Tenant::OPERATIONAL_REACTIVATION_DAYS))
            ->update(['operational_status' => Tenant::OPERATIONAL_EXPIRED]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Tenant $tenant, array $validated, int $actorUserId): Tenant
    {
        $this->syncExpiredOperationalStatuses();
        $tenant->refresh();

        if (! $tenant->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo se puede editar una empresa en estado operativo activo.'],
            ]);
        }

        $payload = $this->onlyUpdatableAttributes($validated);

        return DB::transaction(function () use ($tenant, $payload, $actorUserId) {
            if ($payload !== []) {
                $tenant->fill($payload);
                $tenant->save();
            }

            AdminAuditLogger::tenantCompanyUpdated($actorUserId, $tenant->id);

            return $tenant->fresh();
        });
    }

    public function inactivate(Tenant $tenant, int $actorUserId): Tenant
    {
        $this->syncExpiredOperationalStatuses();
        $tenant->refresh();

        if (! $tenant->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'tenant' => ['Solo se puede inactivar una empresa en estado operativo activo.'],
            ]);
        }

        return DB::transaction(function () use ($tenant, $actorUserId) {
            $tenant->forceFill([
                'operational_status' => Tenant::OPERATIONAL_INACTIVE,
                'inactivated_at' => now(),
                'inactivated_by' => $actorUserId,
            ])->save();

            AdminAuditLogger::tenantCompanyInactivated($actorUserId, $tenant->id);

            return $tenant->fresh();
        });
    }

    public function reactivate(Tenant $tenant, int $actorUserId): Tenant
    {
        $this->syncExpiredOperationalStatuses();
        $tenant->refresh();

        if (! $tenant->canBeReactivated()) {
            throw ValidationException::withMessages([
                'tenant' => ['No se puede reactivar esta empresa (expirada o no elegible).'],
            ]);
        }

        return DB::transaction(function () use ($tenant, $actorUserId) {
            $tenant->forceFill([
                'operational_status' => Tenant::OPERATIONAL_ACTIVE,
                'reactivated_at' => now(),
                'reactivated_by' => $actorUserId,
            ])->save();

            AdminAuditLogger::tenantCompanyReactivated($actorUserId, $tenant->id);

            return $tenant->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function formatTenantCompany(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'codigo' => $tenant->codigo,
            'nombre' => $tenant->nombre,
            'slug' => $tenant->slug,
            'activo' => (bool) $tenant->activo,
            'operational_status' => $tenant->operational_status,
            'inactivated_at' => $tenant->inactivated_at?->toISOString(),
            'reactivated_at' => $tenant->reactivated_at?->toISOString(),
            'inactivated_by' => $tenant->inactivated_by,
            'reactivated_by' => $tenant->reactivated_by,
        ];
    }

    /**
     * Campos editables (excluye identidad fiscal fija, nombre comercial de alta, flags de acceso/suscripción).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function onlyUpdatableAttributes(array $validated): array
    {
        $keys = [
            'slug',
            'tipo_contribuyente',
            'origen_datos',
            'regimen_fiscal_principal',
            'codigo_postal',
            'tipo_vialidad',
            'calle',
            'numero_exterior',
            'numero_interior',
            'colonia',
            'localidad',
            'municipio',
            'estado',
            'correo_electronico',
            'curp',
            'pf_nombre',
            'pf_primer_apellido',
            'pf_segundo_apellido',
            'nombre_comercial',
            'estatus_fiscal',
            'fecha_inicio_operaciones',
            'entre_calle',
            'y_calle',
            'sat_qr_url',
            'constancia_pdf_path',
            'constancia_imagen_path',
            'constancia_emitida_en',
            'constancia_id_cif',
            'regimen_capital',
        ];

        return array_intersect_key($validated, array_flip($keys));
    }
}
