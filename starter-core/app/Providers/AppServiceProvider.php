<?php

namespace App\Providers;

use App\Contracts\Tenancy\TenantResolver;
use App\Models\User;
use App\Observers\UserObserver;
use App\Support\Authorization\Ability;
use App\Support\Tenancy\Resolvers\AuthenticatedUserTenantResolver;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager(
                $app->make(TenantContext::class),
                $app->make(TenantResolver::class),
            );
        });

        $this->app->bind(TenantResolver::class, AuthenticatedUserTenantResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        $this->configureRateLimiting();
        $this->configureAuthorizationGates();
    }

    private function configureAuthorizationGates(): void
    {
        Gate::define(Ability::MANAGE_USERS, function (User $user): bool {
            $slugs = config('authorization.abilities.manage_users', []);

            return $user->hasAnyRoleSlug($slugs);
        });

        Gate::define(Ability::MANAGE_ROLES, function (User $user): bool {
            $slugs = config('authorization.abilities.manage_roles', []);

            return $user->hasAnyRoleSlug($slugs);
        });

        Gate::define(Ability::MANAGE_PLATFORM, function (User $user): bool {
            // Global (sin depender del tenant activo normal).
            return (bool) $user->is_platform_admin;
        });

        Gate::define(Ability::MANAGE_TENANT_COMPANY, function (User $user): bool {
            $slugs = config('authorization.abilities.manage_tenant_company', []);

            return $user->hasAnyRoleSlug($slugs);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('subscription-activation', function (Request $request) {
            $max = 10;
            $decay = 60;

            return (new Limit('', $max, $decay))->by('subscription-activation:'.sha1((string) $request->ip()));
        });

        RateLimiter::for('auth-login-select', function (Request $request) {
            $cfg = config('rate_limiting.auth_login', []);
            $max = (int) ($cfg['max_attempts'] ?? 5);
            $decay = (int) ($cfg['decay_seconds'] ?? 60);

            $key = sha1((string) $request->ip().'|select-tenant|'.(string) $request->input('selection_token', ''));

            return (new Limit('', $max, $decay))->by('auth-login-select:'.$key);
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $cfg = config('rate_limiting.auth_login', []);
            $max = (int) ($cfg['max_attempts'] ?? 5);
            $decay = (int) ($cfg['decay_seconds'] ?? 60);

            $key = sha1(
                (string) $request->ip()
                .'|'.(string) $request->input('tenant_codigo', '')
                .'|'.(string) $request->input('usuario', '')
            );

            return (new Limit('', $max, $decay))->by('auth-login:'.$key);
        });

        RateLimiter::for('auth-refresh', function (Request $request) {
            $cfg = config('rate_limiting.auth_refresh', []);
            $perToken = (int) ($cfg['per_token_max_attempts'] ?? 15);
            $perIp = (int) ($cfg['per_ip_max_attempts'] ?? 40);
            $decay = (int) ($cfg['decay_seconds'] ?? 60);

            $token = (string) $request->input('refresh_token', '');
            $tokenKey = $token !== '' ? hash('sha256', $token) : 'empty';

            return [
                (new Limit('', $perToken, $decay))->by('auth-refresh:token:'.$tokenKey.':'.(string) $request->ip()),
                (new Limit('', $perIp, $decay))->by('auth-refresh:ip:'.(string) $request->ip()),
            ];
        });

        RateLimiter::for('auth-logout', function (Request $request) {
            $cfg = config('rate_limiting.auth_logout', []);
            $max = (int) ($cfg['max_attempts'] ?? 30);
            $decay = (int) ($cfg['decay_seconds'] ?? 60);
            $user = $request->user();
            $tenantId = current_tenant_id() ?? $user?->tenant_id ?? 0;
            $uid = $user?->getAuthIdentifier() ?? 0;

            return (new Limit('', $max, $decay))->by('auth-logout:'.$tenantId.':'.$uid);
        });

        RateLimiter::for('auth-me', function (Request $request) {
            $cfg = config('rate_limiting.auth_me', []);
            $max = (int) ($cfg['max_attempts'] ?? 180);
            $decay = (int) ($cfg['decay_seconds'] ?? 60);
            $user = $request->user();
            $tenantId = current_tenant_id() ?? $user?->tenant_id ?? 0;
            $uid = $user?->getAuthIdentifier() ?? 0;

            return (new Limit('', $max, $decay))->by('auth-me:'.$tenantId.':'.$uid);
        });

        RateLimiter::for('auth-switch-tenant', function (Request $request) {
            $cfg = config('rate_limiting.auth_switch_tenant', []);
            $max = (int) ($cfg['max_attempts'] ?? 40);
            $decay = (int) ($cfg['decay_seconds'] ?? 60);
            $user = $request->user();
            $uid = $user?->getAuthIdentifier() ?? 0;

            return (new Limit('', $max, $decay))->by('auth-switch-tenant:'.$uid.':'.(string) $request->ip());
        });

        RateLimiter::for('admin-users-store', function (Request $request) {
            return $this->adminTenantUserLimit($request, 'admin-users-store', 'admin_users_store');
        });

        RateLimiter::for('admin-user-roles', function (Request $request) {
            return $this->adminTenantUserLimit($request, 'admin-user-roles', 'admin_user_roles');
        });

        RateLimiter::for('admin-roles-store', function (Request $request) {
            return $this->adminTenantUserLimit($request, 'admin-roles-store', 'admin_roles_store');
        });
    }

    private function adminTenantUserLimit(Request $request, string $prefix, string $configKey): Limit
    {
        $cfg = config('rate_limiting.'.$configKey, []);
        $max = (int) ($cfg['max_attempts'] ?? 25);
        $decay = (int) ($cfg['decay_seconds'] ?? 60);
        $user = $request->user();
        $tenantId = current_tenant_id() ?? $user?->tenant_id ?? 0;
        $uid = $user?->getAuthIdentifier() ?? 0;

        return (new Limit('', $max, $decay))->by($prefix.':'.$tenantId.':'.$uid);
    }
}
