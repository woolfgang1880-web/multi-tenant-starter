<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSession;
use App\Support\Logging\SecurityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthSessionService
{
    private const CACHE_PREFIX = 'auth:login_selection:';

    /**
     * Login con tenant explícito (compatibilidad; camino deprecado a favor del login global).
     */
    public function login(string $tenantCodigo, string $usuario, string $password, Request $request): array
    {
        $tenant = Tenant::query()->where('codigo', $tenantCodigo)->where('activo', true)->first();

        if ($tenant === null) {
            SecurityLogger::loginFailed('tenant_not_found', $usuario, $tenantCodigo, $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'credentials'];
        }

        $user = User::query()->where('usuario', $usuario)->first();

        if ($user === null || ! Hash::check($password, $user->getRawOriginal('password_hash'))) {
            SecurityLogger::loginFailed('bad_credentials', $usuario, $tenantCodigo, $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'credentials'];
        }

        if (! $user->belongsToTenantId($tenant->id)) {
            SecurityLogger::loginFailed('bad_credentials', $usuario, $tenantCodigo, $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'credentials'];
        }

        if (! $user->activo) {
            SecurityLogger::loginFailed('user_inactive', $usuario, $tenantCodigo, $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'inactive'];
        }

        return $this->issueSessionTokens($user, $tenant, $request);
    }

    /**
     * Login global: usuario + password. Una empresa → tokens; varias → token de selección en cache.
     */
    public function loginGlobal(string $usuario, string $password, Request $request): array
    {
        $user = User::query()->where('usuario', $usuario)->first();

        if ($user === null || ! Hash::check($password, $user->getRawOriginal('password_hash'))) {
            SecurityLogger::loginFailed('bad_credentials', $usuario, '', $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'credentials'];
        }

        if (! $user->activo) {
            SecurityLogger::loginFailed('user_inactive', $usuario, '', $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'inactive'];
        }

        $tenants = $this->accessibleActiveTenants($user);

        if ($tenants->isEmpty()) {
            SecurityLogger::loginFailed('no_tenant_access', $usuario, '', $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'credentials'];
        }

        if ($tenants->count() === 1) {
            return $this->issueSessionTokens($user, $tenants->first(), $request);
        }

        $ttl = max(60, (int) config('auth-session.login_selection_ttl_seconds', 600));
        $plainSelection = Str::random(64);
        $hash = hash('sha256', $plainSelection);
        Cache::put(self::CACHE_PREFIX.$hash, ['user_id' => $user->id], now()->addSeconds($ttl));

        return [
            'ok' => true,
            'needs_selection' => true,
            'selection_token' => $plainSelection,
            'expires_in' => $ttl,
            'tenants' => $tenants->map(fn (Tenant $t) => [
                'id' => $t->id,
                'codigo' => $t->codigo,
                'nombre' => $t->nombre,
                'slug' => $t->slug,
            ])->values()->all(),
        ];
    }

    /**
     * Completa login tras elegir empresa (token opaco + tenant_codigo).
     */
    public function completeLoginSelection(string $plainSelectionToken, string $tenantCodigo, Request $request): array
    {
        $hash = hash('sha256', $plainSelectionToken);
        $payload = Cache::pull(self::CACHE_PREFIX.$hash);

        if (! is_array($payload) || empty($payload['user_id'])) {
            return ['ok' => false, 'reason' => 'selection_invalid'];
        }

        $user = User::query()->find($payload['user_id']);

        if ($user === null || ! $user->activo) {
            return ['ok' => false, 'reason' => 'selection_invalid'];
        }

        $tenant = Tenant::query()->where('codigo', $tenantCodigo)->where('activo', true)->first();

        if ($tenant === null || ! $user->belongsToTenantId($tenant->id)) {
            SecurityLogger::loginFailed('bad_credentials', $user->usuario, $tenantCodigo, $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'credentials'];
        }

        return $this->issueSessionTokens($user, $tenant, $request);
    }

    /**
     * Empresas activas a las que el usuario puede contextuar sesión (hogar + pivote).
     *
     * @return Collection<int, Tenant>
     */
    public function accessibleActiveTenants(User $user): Collection
    {
        $fromPivot = DB::table('user_tenants')->where('user_id', $user->id)->pluck('tenant_id');
        $ids = collect([$user->tenant_id])->merge($fromPivot)->unique()->filter(fn ($id) => $id !== null);

        return Tenant::query()
            ->whereIn('id', $ids)
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Cambia el tenant activo de la sesión API actual (mismo access token; actualiza user_sessions.tenant_id).
     *
     * @return array{ok: true, tenant: array{id:int, codigo:string, nombre:string, slug:?string}}|array{ok: false, reason: string}
     */
    public function switchSessionTenant(User $user, string $sessionUuid, string $tenantCodigo, Request $request): array
    {
        $tenantCodigo = trim($tenantCodigo);
        if ($tenantCodigo === '') {
            return ['ok' => false, 'reason' => 'tenant_not_found'];
        }

        $tenant = Tenant::query()->where('codigo', $tenantCodigo)->where('activo', true)->first();

        if ($tenant === null) {
            SecurityLogger::tenantSwitchDenied($user->id, null, $sessionUuid, 'tenant_not_found', $request->ip());

            return ['ok' => false, 'reason' => 'tenant_not_found'];
        }

        if (! $user->belongsToTenantId($tenant->id)) {
            SecurityLogger::tenantSwitchDenied($user->id, $tenant->id, $sessionUuid, 'not_member', $request->ip());

            return ['ok' => false, 'reason' => 'forbidden'];
        }

        return DB::transaction(function () use ($user, $tenant, $sessionUuid, $request) {
            $session = UserSession::query()
                ->where('user_id', $user->id)
                ->where('session_uuid', $sessionUuid)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                SecurityLogger::tenantSwitchDenied($user->id, $tenant->id, $sessionUuid, 'session_missing', $request->ip());

                return ['ok' => false, 'reason' => 'session_invalid'];
            }

            if (! $session->is_active || $session->invalidated_at !== null) {
                SecurityLogger::tenantSwitchDenied($user->id, $tenant->id, $sessionUuid, 'session_inactive', $request->ip());

                return ['ok' => false, 'reason' => 'session_invalid'];
            }

            if ($session->expires_at !== null && $session->expires_at->isPast()) {
                SecurityLogger::tenantSwitchDenied($user->id, $tenant->id, $sessionUuid, 'session_expired', $request->ip());

                return ['ok' => false, 'reason' => 'session_invalid'];
            }

            $fromTenantId = $session->tenant_id;
            $session->forceFill([
                'tenant_id' => $tenant->id,
                'last_seen_at' => now(),
            ])->save();

            SecurityLogger::tenantSwitched($user->id, $fromTenantId, $tenant->id, $sessionUuid, $request->ip());

            return [
                'ok' => true,
                'tenant' => [
                    'id' => $tenant->id,
                    'codigo' => $tenant->codigo,
                    'nombre' => $tenant->nombre,
                    'slug' => $tenant->slug,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function issueSessionTokens(User $user, Tenant $tenant, Request $request): array
    {
        return DB::transaction(function () use ($user, $tenant, $request) {
            $this->invalidateExistingSessionsForUser($user);

            $sessionUuid = (string) Str::uuid();
            $accessExpires = now()->addMinutes(config('auth-session.access_ttl_minutes', 60));

            $userSession = UserSession::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'session_uuid' => $sessionUuid,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_active' => true,
                'last_seen_at' => now(),
                'expires_at' => $accessExpires,
                'invalidated_at' => null,
                'invalidation_reason' => null,
            ]);

            $plainAccess = $user->createToken($sessionUuid, ['*'], $accessExpires)->plainTextToken;

            $plainRefresh = Str::random(64);
            RefreshToken::query()->create([
                'user_id' => $user->id,
                'user_session_id' => $userSession->id,
                'token_hash' => hash('sha256', $plainRefresh),
                'expires_at' => now()->addDays(config('auth-session.refresh_ttl_days', 14)),
                'revoked_at' => null,
            ]);

            SecurityLogger::loginSuccess($user->id, $tenant->id, $sessionUuid, $request->ip());

            return [
                'ok' => true,
                'access_token' => $plainAccess,
                'refresh_token' => $plainRefresh,
                'token_type' => 'Bearer',
                'expires_in' => max(1, (int) round($accessExpires->getTimestamp() - now()->getTimestamp())),
                'session_uuid' => $sessionUuid,
            ];
        });
    }

    public function refresh(string $plainRefreshToken, Request $request): array
    {
        $hash = hash('sha256', $plainRefreshToken);

        $record = RefreshToken::query()
            ->with(['userSession', 'user'])
            ->where('token_hash', $hash)
            ->first();

        if ($record === null) {
            SecurityLogger::refreshFailed('not_found', $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'invalid'];
        }

        if ($record->expires_at->lte(now())) {
            SecurityLogger::refreshFailed('expired', $request->ip(), $request->userAgent());

            return ['ok' => false, 'reason' => 'expired'];
        }

        return DB::transaction(function () use ($record, $hash, $request) {
            $record = RefreshToken::query()
                ->with(['userSession', 'user'])
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                SecurityLogger::refreshFailed('not_found', $request->ip(), $request->userAgent());

                return ['ok' => false, 'reason' => 'invalid'];
            }

            if ($record->trashed() || $record->revoked_at !== null || $record->used_at !== null) {
                $this->invalidateSessionOnReuse($record, $request);

                return ['ok' => false, 'reason' => 'invalid'];
            }

            $session = $record->userSession;
            if ($session === null || $session->user_id !== $record->user_id) {
                SecurityLogger::refreshFailed('session_missing', $request->ip(), $request->userAgent());

                return ['ok' => false, 'reason' => 'invalid'];
            }

            if (! $session->is_active || $session->invalidated_at !== null) {
                SecurityLogger::refreshFailed('session_inactive', $request->ip(), $request->userAgent());

                return ['ok' => false, 'reason' => 'session_inactive'];
            }

            $user = $record->user;
            if ($user === null || ! $user->activo) {
                SecurityLogger::refreshFailed('user_invalid', $request->ip(), $request->userAgent());

                return ['ok' => false, 'reason' => 'invalid'];
            }

            $accessExpires = now()->addMinutes(config('auth-session.access_ttl_minutes', 60));

            PersonalAccessToken::query()
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', $user->getMorphClass())
                ->where('name', $session->session_uuid)
                ->delete();

            $session->forceFill([
                'last_seen_at' => now(),
                'expires_at' => $accessExpires,
            ])->save();

            $plainAccess = $user->createToken($session->session_uuid, ['*'], $accessExpires)->plainTextToken;
            $plainRefresh = Str::random(64);

            $newToken = RefreshToken::query()->create([
                'user_id' => $user->id,
                'user_session_id' => $session->id,
                'token_hash' => hash('sha256', $plainRefresh),
                'expires_at' => now()->addDays(config('auth-session.refresh_ttl_days', 14)),
                'revoked_at' => null,
                'used_at' => null,
            ]);

            $record->forceFill([
                'used_at' => now(),
                'replaced_by_token_id' => $newToken->id,
            ])->save();

            SecurityLogger::refreshSuccess($user->id, $session->session_uuid);

            return [
                'ok' => true,
                'access_token' => $plainAccess,
                'refresh_token' => $plainRefresh,
                'token_type' => 'Bearer',
                'expires_in' => max(1, (int) round($accessExpires->getTimestamp() - now()->getTimestamp())),
                'session_uuid' => $session->session_uuid,
            ];
        });
    }

    private function invalidateSessionOnReuse(RefreshToken $record, Request $request): void
    {
        SecurityLogger::refreshReuseDetected(
            $record->user_id,
            $record->userSession?->session_uuid ?? 'unknown',
            $request->ip(),
            $request->userAgent()
        );

        $session = $record->userSession;
        if ($session === null) {
            return;
        }

        UserSession::query()
            ->where('id', $session->id)
            ->update([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => 'reuse_detected',
            ]);

        RefreshToken::query()
            ->where('user_session_id', $session->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $user = $record->user;
        if ($user !== null) {
            PersonalAccessToken::query()
                ->where('tokenable_id', $user->id)
                ->where('tokenable_type', $user->getMorphClass())
                ->where('name', $session->session_uuid)
                ->delete();
        }
    }

    public function logout(User $user, PersonalAccessToken $accessToken): void
    {
        $sessionUuid = $accessToken->name;

        $user->tokens()->where('name', $sessionUuid)->delete();

        $session = UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_uuid', $sessionUuid)
            ->first();

        if ($session !== null) {
            $session->forceFill([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => 'logout',
            ])->save();

            RefreshToken::query()
                ->where('user_session_id', $session->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        SecurityLogger::logout($user->id, $sessionUuid);
    }

    private function invalidateExistingSessionsForUser(User $user): void
    {
        UserSession::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'invalidated_at' => now(),
                'invalidation_reason' => 'superseded_login',
            ]);

        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
