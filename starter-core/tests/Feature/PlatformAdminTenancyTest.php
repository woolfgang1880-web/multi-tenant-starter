<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\ApiErrorCode;
use App\Support\Auth\AuthErrorCode;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAdminTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user, string $tenantCodigo): array
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $tenantCodigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ]);

        $login->assertOk();

        return ['Authorization' => 'Bearer '.$login->json('data.access_token')];
    }

    public function test_platform_create_tenant_ok_for_super_admin_global(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $res = $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant 2',
            'codigo' => 'TENANT2',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TNT010101AA1',
            'nombre_fiscal' => 'TENANT DOS SA DE CV',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ]);

        $res->assertCreated()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('data.codigo', 'TENANT2');

        $this->assertTrue(Tenant::query()->where('codigo', 'TENANT2')->exists());

        // Valida que /auth/me expone la capacidad global para UI.
        $me = $this->withHeaders($h)->getJson('/api/v1/auth/me');
        $me->assertOk()
            ->assertJsonPath('data.user.is_platform_admin', true);
    }

    public function test_platform_list_tenants_ok_for_super_admin_global(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $res = $this->withHeaders($h)->getJson('/api/v1/platform/tenants');

        $res->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('data.total', 3);

        $items = $res->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertContains('DEFAULT', collect($items)->pluck('codigo')->all());
    }

    public function test_platform_list_tenants_forbidden_without_super_admin(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $user = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'not_platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => false,
        ]);

        $h = $this->authHeader($user, 'DEFAULT');

        $res = $this->withHeaders($h)->getJson('/api/v1/platform/tenants');

        $res->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::FORBIDDEN);
    }

    public function test_platform_create_tenant_forbidden_without_super_admin(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $user = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'not_platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => false,
        ]);

        $h = $this->authHeader($user, 'DEFAULT');

        $res = $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant 3',
            'codigo' => 'TENANT3',
            'activo' => true,
        ]);

        $res->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::FORBIDDEN);
    }

    public function test_platform_create_initial_admin_ok(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $tenantRes = $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant 2',
            'codigo' => 'TENANT2',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TNT010101AA2',
            'nombre_fiscal' => 'TENANT DOS SA DE CV',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ]);
        $tenantRes->assertCreated()->assertJsonPath('code', 'OK');

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->where('codigo', 'TENANT2')->firstOrFail();

        $adminRes = $this->withHeaders($h)->postJson('/api/v1/platform/tenants/TENANT2/admins', [
            'admin_usuario' => 'tenant_admin',
            'admin_password' => 'Admin1234!',
            'admin_password_confirmation' => 'Admin1234!',
            'admin_codigo_cliente' => 'CLI-2',
        ]);

        $adminRes->assertCreated()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('data.usuario', 'tenant_admin')
            ->assertJsonPath('data.tenant_id', $tenant->id);

        $admin = User::query()->where('usuario', 'tenant_admin')->firstOrFail();

        $this->assertFalse((bool) $admin->is_platform_admin);
        $this->assertSame($tenant->id, $admin->tenant_id);
        $this->assertTrue(DB::table('user_tenants')->where('user_id', $admin->id)->where('tenant_id', $tenant->id)->exists());

        $this->assertTrue(Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->exists());
        $this->assertTrue($admin->hasRoleSlug('admin'));
    }

    public function test_platform_full_flow_login_admin_created_and_me_roles(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $tenantRes = $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant 2',
            'codigo' => 'TENANT2',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TNT010101AA3',
            'nombre_fiscal' => 'TENANT DOS SA DE CV',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ]);
        $tenantRes->assertCreated()->assertJsonPath('code', 'OK');

        $adminPassword = 'Admin1234!';
        $this->withHeaders($h)->postJson('/api/v1/platform/tenants/TENANT2/admins', [
            'admin_usuario' => 'tenant_admin',
            'admin_password' => $adminPassword,
            'admin_password_confirmation' => $adminPassword,
            'admin_codigo_cliente' => 'CLI-2',
        ])->assertCreated()->assertJsonPath('code', 'OK');

        // Login del admin recién creado en el tenant creado.
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'TENANT2',
            'usuario' => 'tenant_admin',
            'password' => $adminPassword,
        ]);
        $login->assertOk()->assertJsonPath('code', 'OK');
        $token = $login->json('data.access_token');

        $me = $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/v1/auth/me');
        $me->assertOk();
        $me->assertJsonPath('data.tenant.codigo', 'TENANT2');
        $me->assertJsonPath('data.user.is_platform_admin', false);

        $roles = $me->json('data.user.roles');
        $this->assertIsArray($roles);
        $this->assertNotEmpty($roles);
        $slugs = collect($roles)->pluck('slug')->all();
        $this->assertContains('admin', $slugs);
    }

    public function test_platform_create_tenant_rejects_duplicate_codigo(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant 2',
            'codigo' => 'TENANT2',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TNT010101AA4',
            'nombre_fiscal' => 'TENANT DOS SA DE CV',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ])->assertCreated()->assertJsonPath('code', 'OK');

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant 2 Duplicate',
            'codigo' => 'TENANT2',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TNT010101AA5',
            'nombre_fiscal' => 'TENANT DOS SA DE CV',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ])->assertStatus(422)->assertJsonPath('code', ApiErrorCode::VALIDATION_ERROR);
    }
}

