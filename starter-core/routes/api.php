<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LoginSelectTenantController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RefreshController;
use App\Http\Controllers\Api\V1\Auth\SwitchTenantController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantCreateController;
use App\Http\Controllers\Api\V1\Platform\PlatformTenantInitialAdminCreateController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ReadinessController;
use App\Http\Controllers\Api\V1\Roles\RoleController;
use App\Http\Controllers\Api\V1\Users\DeactivateUserController;
use App\Http\Controllers\Api\V1\Users\UserController;
use App\Http\Controllers\Api\V1\Users\UserRolesController;
use App\Support\Authorization\Ability;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API (API-first)
|--------------------------------------------------------------------------
| Rutas bajo el prefijo global /api (bootstrap) y versionadas en /v1.
| Ejemplo: GET /api/v1/health
|
| Contexto multi-tenant: tras autenticación, usar middleware `tenant.context`
| (ver docs/TENANCY.md). Rutas públicas como /health no lo requieren.
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');
    Route::get('/ready', ReadinessController::class)->name('api.v1.ready');

    Route::prefix('auth')->group(function (): void {
        Route::post('login', LoginController::class)
            ->middleware('throttle:auth-login')
            ->name('api.v1.auth.login');

        Route::post('login/select-tenant', LoginSelectTenantController::class)
            ->middleware('throttle:auth-login-select')
            ->name('api.v1.auth.login.select_tenant');

        Route::post('refresh', RefreshController::class)
            ->middleware('throttle:auth-refresh')
            ->name('api.v1.auth.refresh');

        Route::post('logout', LogoutController::class)
            ->middleware(['auth:sanctum', 'tenant.context', 'throttle:auth-logout'])
            ->name('api.v1.auth.logout');

        Route::get('me', MeController::class)
            ->middleware(['auth:sanctum', 'tenant.context', 'active.api.session', 'throttle:auth-me'])
            ->name('api.v1.auth.me');

        Route::post('switch-tenant', SwitchTenantController::class)
            ->middleware(['auth:sanctum', 'tenant.context', 'active.api.session', 'throttle:auth-switch-tenant'])
            ->name('api.v1.auth.switch_tenant');
    });

    Route::middleware([
        'auth:sanctum',
        'tenant.context',
        'active.api.session',
        'can:'.Ability::MANAGE_USERS,
    ])->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('api.v1.users.index');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('throttle:admin-users-store')
            ->name('api.v1.users.store');
        Route::get('users/{id}', [UserController::class, 'show'])->whereNumber('id')->name('api.v1.users.show');
        Route::put('users/{id}', [UserController::class, 'update'])->whereNumber('id')->name('api.v1.users.update');
        Route::patch('users/{id}/deactivate', DeactivateUserController::class)->whereNumber('id')->name('api.v1.users.deactivate');

        Route::post('users/{id}/roles', [UserRolesController::class, 'attach'])
            ->middleware('throttle:admin-user-roles')
            ->whereNumber('id')
            ->name('api.v1.users.roles.attach');
        Route::put('users/{id}/roles', [UserRolesController::class, 'sync'])
            ->middleware('throttle:admin-user-roles')
            ->whereNumber('id')
            ->name('api.v1.users.roles.sync');
    });

    Route::middleware([
        'auth:sanctum',
        'tenant.context',
        'active.api.session',
        'can:'.Ability::MANAGE_ROLES,
    ])->group(function (): void {
        Route::get('roles', [RoleController::class, 'index'])->name('api.v1.roles.index');
        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('throttle:admin-roles-store')
            ->name('api.v1.roles.store');
        Route::get('roles/{id}', [RoleController::class, 'show'])->whereNumber('id')->name('api.v1.roles.show');
        Route::put('roles/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('api.v1.roles.update');
    });

    Route::prefix('platform')->middleware([
        'auth:sanctum',
        'active.api.session',
        'can:'.Ability::MANAGE_PLATFORM,
    ])->group(function (): void {
        Route::post('tenants', PlatformTenantCreateController::class)->name('api.v1.platform.tenants.store');

        Route::post('tenants/{tenant_codigo}/admins', PlatformTenantInitialAdminCreateController::class)
            ->where('tenant_codigo', '[A-Za-z0-9_-]+')
            ->name('api.v1.platform.tenants.admins.store');
    });
});
