<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthErrorCode;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PASO 4 — Rate limiting por riesgo (límites bajos vía config en cada test).
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config([
            'rate_limiting.auth_login.max_attempts' => (int) env('RATE_LIMIT_AUTH_LOGIN_MAX', 5),
            'rate_limiting.auth_refresh.per_token_max_attempts' => (int) env('RATE_LIMIT_REFRESH_PER_TOKEN_MAX', 15),
            'rate_limiting.auth_refresh.per_ip_max_attempts' => (int) env('RATE_LIMIT_REFRESH_PER_IP_MAX', 40),
            'rate_limiting.auth_me.max_attempts' => (int) env('RATE_LIMIT_ME_MAX', 180),
            'rate_limiting.admin_users_store.max_attempts' => (int) env('RATE_LIMIT_ADMIN_USER_CREATE_MAX', 25),
            'rate_limiting.admin_user_roles.max_attempts' => (int) env('RATE_LIMIT_ADMIN_USER_ROLES_MAX', 80),
        ]);

        parent::tearDown();
    }

    private function seedAdmin(): User
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'admin',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $role = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin->fresh(['tenant']);
    }

    private function authHeader(User $user): array
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $user->tenant->codigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ]);
        $login->assertOk();

        return ['Authorization' => 'Bearer '.$login->json('data.access_token')];
    }

    public function test_login_throttled_after_max_attempts(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'victim',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        config(['rate_limiting.auth_login.max_attempts' => 2]);

        $payload = [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'victim',
            'password' => 'wrong',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('code', AuthErrorCode::TOO_MANY_ATTEMPTS);
    }

    public function test_refresh_throttled_after_abuse_same_token_body(): void
    {
        config([
            'rate_limiting.auth_refresh.per_token_max_attempts' => 2,
            'rate_limiting.auth_refresh.per_ip_max_attempts' => 100,
        ]);

        $garbage = str_repeat('b', 64);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $garbage])->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $garbage])->assertStatus(401);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $garbage])
            ->assertStatus(429)
            ->assertJsonPath('code', AuthErrorCode::TOO_MANY_ATTEMPTS);
    }

    public function test_admin_create_user_throttled_per_actor(): void
    {
        config(['rate_limiting.admin_users_store.max_attempts' => 2]);

        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'u1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-1',
        ])->assertCreated();

        $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'u2',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-2',
        ])->assertCreated();

        $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'u3',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-3',
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', AuthErrorCode::TOO_MANY_ATTEMPTS);
    }

    public function test_login_quota_isolated_per_tenant_and_usuario(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $other = Tenant::factory()->create(['codigo' => 'OTHER', 'nombre' => 'Otra', 'slug' => 'otra']);

        User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'alice',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        User::factory()->create([
            'tenant_id' => $other->id,
            'usuario' => 'bob',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        config(['rate_limiting.auth_login.max_attempts' => 2]);

        $aliceFail = [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'alice',
            'password' => 'nope',
        ];
        $this->postJson('/api/v1/auth/login', $aliceFail)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $aliceFail)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $aliceFail)->assertStatus(429);

        $bobOk = [
            'tenant_codigo' => 'OTHER',
            'usuario' => 'bob',
            'password' => 'password',
        ];
        $this->postJson('/api/v1/auth/login', $bobOk)->assertOk();
    }

    public function test_admin_create_user_quota_isolated_per_tenant_actor(): void
    {
        config(['rate_limiting.admin_users_store.max_attempts' => 2]);

        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $t1 = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $t2 = Tenant::factory()->create(['codigo' => 'T2', 'nombre' => 'Dos', 'slug' => 'dos']);
        $adminRole = Role::query()->where('tenant_id', $t1->id)->where('slug', 'admin')->firstOrFail();
        $adminRole2 = Role::factory()->create([
            'tenant_id' => $t2->id,
            'nombre' => 'Admin',
            'slug' => 'admin',
        ]);

        $admin1 = User::factory()->create([
            'tenant_id' => $t1->id,
            'usuario' => 'a1',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin1->roles()->syncWithoutDetaching([$adminRole->id]);

        $admin2 = User::factory()->create([
            'tenant_id' => $t2->id,
            'usuario' => 'a2',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin2->roles()->syncWithoutDetaching([$adminRole2->id]);

        $h1 = $this->authHeader($admin1->fresh('tenant'));
        $this->withHeaders($h1)->postJson('/api/v1/users', [
            'usuario' => 'x1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-1',
        ])->assertCreated();
        $this->withHeaders($h1)->postJson('/api/v1/users', [
            'usuario' => 'x2',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-2',
        ])->assertCreated();
        $this->withHeaders($h1)->postJson('/api/v1/users', [
            'usuario' => 'x3',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-3',
        ])->assertStatus(429);

        $h2 = $this->authHeader($admin2->fresh('tenant'));
        $this->withHeaders($h2)->postJson('/api/v1/users', [
            'usuario' => 'y1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'D-1',
        ])->assertCreated();
    }

    public function test_auth_me_throttled_after_excess(): void
    {
        config(['rate_limiting.auth_me.max_attempts' => 3]);

        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->getJson('/api/v1/auth/me')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/auth/me')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/auth/me')->assertOk();

        $this->withHeaders($h)->getJson('/api/v1/auth/me')
            ->assertStatus(429)
            ->assertJsonPath('code', AuthErrorCode::TOO_MANY_ATTEMPTS);
    }

    public function test_admin_sync_roles_throttled_per_actor(): void
    {
        config(['rate_limiting.admin_user_roles.max_attempts' => 2]);

        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);
        $role = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();

        $u = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'usuario' => 'target',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$u->id.'/roles', [
            'role_ids' => [$role->id],
        ])->assertOk();

        $this->withHeaders($h)->putJson('/api/v1/users/'.$u->id.'/roles', [
            'role_ids' => [],
        ])->assertOk();

        $this->withHeaders($h)->putJson('/api/v1/users/'.$u->id.'/roles', [
            'role_ids' => [$role->id],
        ])
            ->assertStatus(429)
            ->assertJsonPath('code', AuthErrorCode::TOO_MANY_ATTEMPTS);
    }
}
