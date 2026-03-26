<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuario con identidad global (`usuario` único). Puede pertenecer a varias empresas
 * vía `user_tenants`; `tenant_id` es el tenant principal. Autorización: roles por
 * tenant en `user_roles`; el tenant activo de la petición viene de la sesión API.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'codigo_cliente',
        'usuario',
        'password_hash',
        'activo',
        'is_platform_admin',
        'fecha_alta',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'fecha_alta' => 'date',
            'activo' => 'boolean',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Empresas a las que el usuario pertenece (N:N). `tenant_id` en users es el tenant “principal”.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants')->withTimestamps();
    }

    /**
     * Miembro del tenant: pivote `user_tenants` o, en compatibilidad, `users.tenant_id` (datos previos a la Fase 1 o sin fila pivote).
     */
    public function belongsToTenantId(int $tenantId): bool
    {
        if ((int) $this->tenant_id === $tenantId) {
            return true;
        }

        return $this->tenants()->where('tenants.id', $tenantId)->exists();
    }

    /**
     * Empresa del contexto activo (sesión) para respuestas de perfil; evita consultas sueltas en controladores.
     */
    public function tenantForActiveContext(?int $activeTenantId): ?Tenant
    {
        if ($activeTenantId === null) {
            return $this->tenant;
        }

        if ((int) $this->tenant_id === $activeTenantId) {
            return $this->tenant;
        }

        return $this->tenants()->where('tenants.id', $activeTenantId)->first();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('created_at');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Roles en el tenant activo del request (sesión) o, si no hay contexto HTTP, en `users.tenant_id`.
     */
    public function rolesForTenant(): BelongsToMany
    {
        $tenantId = current_tenant_id() ?? $this->tenant_id;
        if ($tenantId === null) {
            return $this->roles()->whereRaw('1 = 0');
        }

        return $this->roles()->where('roles.tenant_id', $tenantId);
    }

    public function hasRoleSlug(string $slug): bool
    {
        return $this->rolesForTenant()->where('roles.slug', $slug)->exists();
    }

    /**
     * @param  list<string>  $slugs
     */
    public function hasAnyRoleSlug(array $slugs): bool
    {
        if ($slugs === []) {
            return false;
        }

        return $this->rolesForTenant()->whereIn('roles.slug', $slugs)->exists();
    }
}
