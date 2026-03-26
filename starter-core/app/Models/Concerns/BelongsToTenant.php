<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Modelos con columna `tenant_id`: consultas explícitas por tenant.
 *
 * No aplica global scopes aquí (FASE 3): en fases siguientes se puede añadir
 * `bootBelongsToTenant()` con `addGlobalScope` filtrando por TenantManager::id()
 * o políticas equivalentes, sin rehacer esta base.
 */
trait BelongsToTenant
{
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
    }
}
