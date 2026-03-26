<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa/cliente (tenant). El contexto activo en HTTP se resuelve vía usuario
 * autenticado (ver `docs/TENANCY.md`).
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public const SUBSCRIPTION_TRIAL = 'trial';

    public const SUBSCRIPTION_ACTIVE = 'active';

    public const SUBSCRIPTION_EXPIRED = 'expired';

    public const SUBSCRIPTION_SUSPENDED = 'suspended';

    protected $fillable = [
        'codigo',
        'nombre',
        'slug',
        'activo',
        'trial_starts_at',
        'trial_ends_at',
        'subscription_status',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
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
}
