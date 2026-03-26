<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthErrorCode;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;
use Tests\Support\OpenApiContractAsserts;
use Tests\TestCase;

/**
 * Contract tests pragmáticos frente a docs/openapi/openapi.yaml (PASO 5).
 */
class OpenApiContractTest extends TestCase
{
    use OpenApiContractAsserts;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config([
            'rate_limiting.auth_login.max_attempts' => (int) env('RATE_LIMIT_AUTH_LOGIN_MAX', 5),
            'rate_limiting.auth_refresh.per_token_max_attempts' => (int) env('RATE_LIMIT_REFRESH_PER_TOKEN_MAX', 15),
            'rate_limiting.auth_refresh.per_ip_max_attempts' => (int) env('RATE_LIMIT_REFRESH_PER_IP_MAX', 40),
            'rate_limiting.admin_users_store.max_attempts' => (int) env('RATE_LIMIT_ADMIN_USER_CREATE_MAX', 25),
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
        $r = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$r->id]);

        return $admin->fresh(['tenant']);
    }

    private function seedBasicUser(): User
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $u = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'basic',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $r = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'user')->firstOrFail();
        $u->roles()->syncWithoutDetaching([$r->id]);

        return $u->fresh(['tenant']);
    }

    private function bearer(User $user): array
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $user->tenant->codigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ]);
        $login->assertOk();

        return ['Authorization' => 'Bearer '.$login->json('data.access_token')];
    }

    public function test_contract_auth_login_200_matches_token_envelope(): void
    {
        $this->seedAdmin();
        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $res->assertOk();
        $j = $res->json();
        $this->assertApiEnvelopeShape($j);
        Assert::assertSame('OK', $j['code']);
        $this->assertTokenPayloadShape($j['data']);
    }

    public function test_contract_auth_login_global_200_matches_token_envelope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $res = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'admin_demo',
            'password' => 'Admin123!',
        ]);
        $res->assertOk();
        $j = $res->json();
        $this->assertApiEnvelopeShape($j);
        Assert::assertSame('OK', $j['code']);
        $this->assertTokenPayloadShape($j['data']);
    }

    public function test_contract_auth_login_global_200_tenant_selection_envelope(): void
    {
        $this->seed(DatabaseSeeder::class);
        $res = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $res->assertOk();
        $j = $res->json();
        $this->assertApiEnvelopeShape($j);
        Assert::assertSame('TENANT_SELECTION_REQUIRED', $j['code']);
        Assert::assertIsArray($j['data']);
        Assert::assertArrayHasKey('selection_token', $j['data']);
        Assert::assertArrayHasKey('expires_in', $j['data']);
        Assert::assertArrayHasKey('tenants', $j['data']);
        Assert::assertIsArray($j['data']['tenants']);
        Assert::assertGreaterThanOrEqual(1, count($j['data']['tenants']));
    }

    public function test_contract_auth_login_401_invalid_credentials_envelope(): void
    {
        $this->seedAdmin();
        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'wrong-password',
        ]);
        $res->assertStatus(401);
        $this->assertApiErrorEnvelope($res->json(), AuthErrorCode::INVALID_CREDENTIALS);
    }

    public function test_contract_auth_login_403_inactive_envelope(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'inactivo',
            'password_hash' => 'password',
            'activo' => false,
        ]);
        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'inactivo',
            'password' => 'password',
        ]);
        $res->assertStatus(403);
        $this->assertApiErrorEnvelope($res->json(), AuthErrorCode::ACCOUNT_INACTIVE);
    }

    public function test_contract_auth_login_422_validation_envelope(): void
    {
        $res = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'x',
        ]);
        $res->assertStatus(422);
        $this->assertApiValidationEnvelope($res->json());
    }

    public function test_contract_auth_login_429_envelope(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'u',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        config(['rate_limiting.auth_login.max_attempts' => 2]);
        $p = ['tenant_codigo' => 'DEFAULT', 'usuario' => 'u', 'password' => 'bad'];
        $this->postJson('/api/v1/auth/login', $p)->assertStatus(401);
        $this->postJson('/api/v1/auth/login', $p)->assertStatus(401);
        $r = $this->postJson('/api/v1/auth/login', $p);
        $r->assertStatus(429);
        $this->assertApiErrorEnvelope($r->json(), AuthErrorCode::TOO_MANY_ATTEMPTS);
    }

    public function test_contract_auth_refresh_200_and_401_envelopes(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $login->assertOk();
        $refresh = $login->json('data.refresh_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r1->assertOk();
        $j = $r1->json();
        $this->assertApiEnvelopeShape($j);
        Assert::assertSame('OK', $j['code']);
        $this->assertTokenPayloadShape($j['data']);

        $r2 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r2->assertStatus(401);
        $this->assertApiErrorEnvelope($r2->json(), AuthErrorCode::REFRESH_INVALID);
    }

    public function test_contract_auth_refresh_422_validation_envelope(): void
    {
        $res = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'short']);
        $res->assertStatus(422);
        $this->assertApiValidationEnvelope($res->json());
    }

    public function test_contract_auth_refresh_429_envelope(): void
    {
        config([
            'rate_limiting.auth_refresh.per_token_max_attempts' => 2,
            'rate_limiting.auth_refresh.per_ip_max_attempts' => 100,
        ]);
        $t = str_repeat('c', 64);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $t])->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $t])->assertStatus(401);
        $r = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $t]);
        $r->assertStatus(429);
        $this->assertApiErrorEnvelope($r->json(), AuthErrorCode::TOO_MANY_ATTEMPTS);
    }

    public function test_contract_auth_logout_200_and_401_envelopes(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $token = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ])->json('data.access_token');

        $out = $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/auth/logout');
        $out->assertOk();
        $j = $out->json();
        $this->assertApiEnvelopeShape($j);
        Assert::assertSame('OK', $j['code']);
        Assert::assertNull($j['data']);

        $noAuth = $this->withoutToken()->postJson('/api/v1/auth/logout');
        $noAuth->assertStatus(401);
        $this->assertApiErrorEnvelope($noAuth->json(), AuthErrorCode::UNAUTHENTICATED);
    }

    public function test_contract_auth_me_200_and_401_envelopes(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $me = $this->withHeaders($h)->getJson('/api/v1/auth/me');
        $me->assertOk();
        $j = $me->json();
        $this->assertApiEnvelopeShape($j);
        Assert::assertSame('OK', $j['code']);
        $this->assertMeDataShape($j['data']);

        $noMe = $this->withoutToken()->getJson('/api/v1/auth/me');
        $noMe->assertStatus(401);
        $this->assertApiErrorEnvelope($noMe->json(), AuthErrorCode::UNAUTHENTICATED);
    }

    public function test_contract_auth_switch_tenant_200_and_403_envelopes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ])->json('data.access_token');

        $h = ['Authorization' => 'Bearer '.$token];
        $ok = $this->withHeaders($h)->postJson('/api/v1/auth/switch-tenant', ['tenant_codigo' => 'PRUEBA1']);
        $ok->assertOk();
        $j = $ok->json();
        $this->assertApiEnvelopeShape($j);
        \PHPUnit\Framework\Assert::assertSame('OK', $j['code']);
        \PHPUnit\Framework\Assert::assertIsArray($j['data']);
        \PHPUnit\Framework\Assert::assertSame('PRUEBA1', $j['data']['tenant']['codigo']);

        $forbidden = $this->withHeaders($h)->postJson('/api/v1/auth/switch-tenant', ['tenant_codigo' => 'PRUEBAS']);
        $forbidden->assertStatus(403);
        $this->assertApiErrorEnvelope($forbidden->json(), AuthErrorCode::FORBIDDEN);
    }

    public function test_contract_users_store_201_put_200_and_errors(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);

        $create = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'op',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-1',
        ]);
        $create->assertCreated();
        $cj = $create->json();
        $this->assertApiEnvelopeShape($cj);
        Assert::assertSame('OK', $cj['code']);
        $this->assertUserWithRolesShape($cj['data']);

        $id = $cj['data']['id'];
        $upd = $this->withHeaders($h)->putJson('/api/v1/users/'.$id, [
            'usuario' => 'op',
            'codigo_cliente' => 'C-2',
        ]);
        $upd->assertOk();
        $uj = $upd->json();
        $this->assertApiEnvelopeShape($uj);
        $this->assertUserWithRolesShape($uj['data']);
        Assert::assertSame('C-2', $uj['data']['codigo_cliente']);

        $bad = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'nopass',
            'codigo_cliente' => 'X',
        ]);
        $bad->assertStatus(422);
        $this->assertApiValidationEnvelope($bad->json());

        $other = Tenant::factory()->create(['codigo' => 'X1', 'nombre' => 'X', 'slug' => 'x']);
        $foreign = User::factory()->create(['tenant_id' => $other->id, 'usuario' => 'f', 'password_hash' => 'password']);
        $this->withHeaders($h)->getJson('/api/v1/users/'.$foreign->id)
            ->assertStatus(404);
        $this->assertApiErrorEnvelope(
            $this->withHeaders($h)->getJson('/api/v1/users/'.$foreign->id)->json(),
            'NOT_FOUND'
        );
    }

    public function test_contract_users_403_forbidden_envelope(): void
    {
        $basic = $this->seedBasicUser();
        $h = $this->bearer($basic);
        $r = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'nope',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $r->assertStatus(403);
        $this->assertApiErrorEnvelope($r->json(), AuthErrorCode::FORBIDDEN);
    }

    public function test_contract_roles_store_201_envelope(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $res = $this->withHeaders($h)->postJson('/api/v1/roles', [
            'nombre' => 'Auditor',
            'slug' => 'auditor',
            'descripcion' => 'Lectura',
        ]);
        $res->assertCreated();
        $j = $res->json();
        $this->assertApiEnvelopeShape($j);
        $this->assertRoleResourceShape($j['data']);
        Assert::assertSame('auditor', $j['data']['slug']);
    }

    public function test_contract_roles_403_forbidden_envelope(): void
    {
        $basic = $this->seedBasicUser();
        $h = $this->bearer($basic);
        $r = $this->withHeaders($h)->postJson('/api/v1/roles', [
            'nombre' => 'X',
            'slug' => 'x_role',
        ]);
        $r->assertStatus(403);
        $this->assertApiErrorEnvelope($r->json(), AuthErrorCode::FORBIDDEN);
    }

    public function test_contract_user_roles_sync_and_attach_envelopes(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $role = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();

        $u = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'usuario' => 'target',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $sync = $this->withHeaders($h)->putJson('/api/v1/users/'.$u->id.'/roles', [
            'role_ids' => [$role->id],
        ]);
        $sync->assertOk();
        $sj = $sync->json();
        $this->assertUserWithRolesShape($sj['data']);
        Assert::assertSame('user', $sj['data']['roles'][0]['slug'] ?? null);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$u->id.'/roles', ['role_ids' => []])->assertOk();

        $attach = $this->withHeaders($h)->postJson('/api/v1/users/'.$u->id.'/roles', [
            'role_ids' => [$role->id],
        ]);
        $attach->assertOk();
        $this->assertUserWithRolesShape($attach->json()['data']);
    }

    public function test_contract_user_roles_404_and_422_envelopes(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $other = Tenant::factory()->create(['codigo' => 'Z1', 'nombre' => 'Z', 'slug' => 'z']);
        $foreign = User::factory()->create(['tenant_id' => $other->id, 'usuario' => 'v', 'password_hash' => 'password']);
        $r = $this->withHeaders($h)->putJson('/api/v1/users/'.$foreign->id.'/roles', ['role_ids' => []]);
        $r->assertStatus(404);
        $this->assertApiErrorEnvelope($r->json(), 'NOT_FOUND');

        $local = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'usuario' => 'loc',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $bad = $this->withHeaders($h)->postJson('/api/v1/users/'.$local->id.'/roles', ['role_ids' => []]);
        $bad->assertStatus(422);
        $this->assertApiValidationEnvelope($bad->json());
    }

    public function test_contract_users_index_list_envelope(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $res = $this->withHeaders($h)->getJson('/api/v1/users?per_page=5');
        $res->assertOk();
        $j = $res->json();
        $this->assertApiEnvelopeShape($j);
        $this->assertUserListDataShape($j['data']);
    }

    public function test_contract_admin_post_users_429_envelope(): void
    {
        config(['rate_limiting.admin_users_store.max_attempts' => 2]);
        $admin = $this->seedAdmin();
        $h = $this->bearer($admin);
        $body = fn (string $u) => [
            'usuario' => $u,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => $u,
        ];
        $this->withHeaders($h)->postJson('/api/v1/users', $body('a1'))->assertCreated();
        $this->withHeaders($h)->postJson('/api/v1/users', $body('a2'))->assertCreated();
        $r = $this->withHeaders($h)->postJson('/api/v1/users', $body('a3'));
        $r->assertStatus(429);
        $this->assertApiErrorEnvelope($r->json(), AuthErrorCode::TOO_MANY_ATTEMPTS);
    }
}
