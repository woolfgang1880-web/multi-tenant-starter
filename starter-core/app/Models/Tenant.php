<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa/cliente (tenant). El contexto activo en HTTP se resuelve vía usuario
 * autenticado (ver `docs/TENANCY.md`).
 *
 * Estado operativo (`operational_status`) es independiente de `activo` y del login;
 * no modificar `allowsApiAccess()` salvo decisión de producto explícita.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public const SUBSCRIPTION_TRIAL = 'trial';

    public const SUBSCRIPTION_ACTIVE = 'active';

    public const SUBSCRIPTION_EXPIRED = 'expired';

    public const SUBSCRIPTION_SUSPENDED = 'suspended';

    public const OPERATIONAL_ACTIVE = 'active';

    public const OPERATIONAL_INACTIVE = 'inactive';

    public const OPERATIONAL_EXPIRED = 'expired';

    /** Ventana para reactivar tras inactivación operativa (días). */
    public const OPERATIONAL_REACTIVATION_DAYS = 30;

    protected $fillable = [
        'codigo',
        'nombre',
        'slug',
        'activo',
        'operational_status',
        'trial_starts_at',
        'trial_ends_at',
        'subscription_status',
        'tipo_contribuyente',
        'origen_datos',
        'rfc',
        'nombre_fiscal',
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

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'inactivated_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'fecha_inicio_operaciones' => 'date',
            'constancia_emitida_en' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function inactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inactivated_by');
    }

    public function reactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    public function scopeActiveOperationally(Builder $query): Builder
    {
        return $query->where('operational_status', self::OPERATIONAL_ACTIVE);
    }

    public function scopeInactiveOperationally(Builder $query): Builder
    {
        return $query->where('operational_status', self::OPERATIONAL_INACTIVE);
    }

    /**
     * Listados operativos: activas + inactivas aún no expiradas (no incluye `expired`).
     */
    public function scopeVisibleOperationally(Builder $query): Builder
    {
        return $query->whereIn('operational_status', [
            self::OPERATIONAL_ACTIVE,
            self::OPERATIONAL_INACTIVE,
        ]);
    }

    public function isOperationallyActive(): bool
    {
        return $this->operational_status === self::OPERATIONAL_ACTIVE;
    }

    public function isOperationallyInactive(): bool
    {
        return $this->operational_status === self::OPERATIONAL_INACTIVE;
    }

    public function isOperationallyExpired(): bool
    {
        if ($this->operational_status === self::OPERATIONAL_EXPIRED) {
            return true;
        }

        return $this->shouldBeExpired();
    }

    /**
     * Inactiva ≥ 30 días: debe tratarse como expirada (aunque el job masivo aún no haya corrido).
     */
    public function shouldBeExpired(): bool
    {
        if ($this->operational_status !== self::OPERATIONAL_INACTIVE) {
            return false;
        }

        if ($this->inactivated_at === null) {
            return false;
        }

        return $this->inactivated_at->lte(now()->subDays(self::OPERATIONAL_REACTIVATION_DAYS));
    }

    /**
     * Puede reactivarse solo desde inactive y dentro de la ventana de 30 días.
     */
    public function canBeReactivated(): bool
    {
        if ($this->operational_status === self::OPERATIONAL_EXPIRED) {
            return false;
        }

        if ($this->operational_status !== self::OPERATIONAL_INACTIVE) {
            return false;
        }

        if ($this->inactivated_at === null) {
            return false;
        }

        return $this->inactivated_at->gt(now()->subDays(self::OPERATIONAL_REACTIVATION_DAYS));
    }

    /**
     * ¿Puede esta empresa emitir sesiones API (login, refresh, switch) con acceso permitido?
     *
     * Reglas (fase 1 — trial / suscripción, sin lógica comercial):
     * - `activo = false` → sin acceso.
     * - `subscription_status` null/vacío → acceso (compatibilidad: empresas sin dato de facturación).
     * - `active` → acceso.
     * - `suspended` / `expired` → sin acceso.
     * - `trial` → acceso si no hay `trial_ends_at` o la fecha fin aún no pasó (comparación inclusive con `now()`).
     */
    public function allowsApiAccess(): bool
    {
        if (! $this->activo) {
            return false;
        }

        $status = $this->subscription_status;

        if ($status === null || $status === '') {
            return true;
        }

        return match ($status) {
            self::SUBSCRIPTION_ACTIVE => true,
            self::SUBSCRIPTION_SUSPENDED => false,
            self::SUBSCRIPTION_EXPIRED => false,
            self::SUBSCRIPTION_TRIAL => $this->trialPeriodStillValid(),
            default => true,
        };
    }

    private function trialPeriodStillValid(): bool
    {
        if ($this->trial_ends_at === null) {
            return true;
        }

        return now()->lessThanOrEqualTo($this->trial_ends_at);
    }
}
