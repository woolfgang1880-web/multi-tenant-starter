<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Metrics\OperationalMetrics;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationalMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function metrics(): OperationalMetrics
    {
        return app(OperationalMetrics::class);
    }

    protected function tearDown(): void
    {
        config([
            'metrics.store' => null,
        ]);

        parent::tearDown();
    }

    private function seedAdmin(): User
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'admin',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        return $admin->fresh(['tenant']);
    }

    private function authHeader(User $user): array
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $user->tenant->codigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ])->assertOk();

        return ['Authorization' => 'Bearer '.$login->json('data.access_token')];
    }

    public function test_auth_metrics_are_incremented_in_key_flows(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'u',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'u',
            'password' => 'bad',
        ])->assertStatus(401);

        $this->assertSame(1, (int) Cache::get($this->metrics()->keyFor('auth.login.failed', ['reason' => 'bad_credentials'])));

        $admin = $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ])->assertOk();
        $refresh = $login->json('data.refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])->assertOk();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => str_repeat('z', 64)])->assertStatus(401);

        $this->assertGreaterThanOrEqual(1, (int) Cache::get($this->metrics()->keyFor('auth.refresh.success')));
        $this->assertGreaterThanOrEqual(1, (int) Cache::get($this->metrics()->keyFor('auth.refresh.reuse_detected')));
        $this->assertGreaterThanOrEqual(1, (int) Cache::get($this->metrics()->keyFor('auth.refresh.failed', ['reason' => 'not_found'])));
    }

    public function test_auth_rate_limited_metric_is_incremented(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'limit',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        config(['rate_limiting.auth_login.max_attempts' => 2]);
        $payload = ['tenant_codigo' => 'DEFAULT', 'usuario' => 'limit', 'password' => 'bad'];
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);

        $this->assertGreaterThanOrEqual(1, (int) Cache::get($this->metrics()->keyFor('auth.rate_limited', ['endpoint' => 'login'])));
    }

    public function test_admin_metrics_are_incremented(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);
        $role = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();

        $create = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'm_user',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'M-1',
        ])->assertCreated();
        $userId = $create->json('data.id');

        $this->withHeaders($h)->putJson('/api/v1/users/'.$userId.'/roles', ['role_ids' => [$role->id]])->assertOk();
        $this->withHeaders($h)->patchJson('/api/v1/users/'.$userId.'/deactivate')->assertOk();

        $this->assertGreaterThanOrEqual(1, (int) Cache::get($this->metrics()->keyFor('admin.user.created')));
        $this->assertGreaterThanOrEqual(1, (int) Cache::get($this->metrics()->keyFor('admin.user.deactivated')));
    }

    public function test_readiness_degraded_metric_is_incremented_when_cache_check_fails(): void
    {
        config(['metrics.store' => 'array']);
        config(['cache.default' => 'null']);

        $this->getJson('/api/v1/ready')->assertStatus(503);

        $this->assertGreaterThanOrEqual(1, (int) Cache::store('array')->get($this->metrics()->keyFor('readiness.degraded')));
    }
}

