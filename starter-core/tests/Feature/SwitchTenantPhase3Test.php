<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3 — cambio de tenant con sesión ya autenticada (sin re-login).
 */
class SwitchTenantPhase3Test extends TestCase
{
    use RefreshDatabase;

    public function test_me_includes_accessible_tenants_for_multi_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $login->assertOk();
        $token = $login->json('data.access_token');

        $me = $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/v1/auth/me');
        $me->assertOk();
        $tenants = $me->json('data.accessible_tenants');
        $this->assertIsArray($tenants);
        $this->assertGreaterThanOrEqual(2, count($tenants));
        $codes = collect($tenants)->pluck('codigo')->all();
        $this->assertContains('DEFAULT', $codes);
        $this->assertContains('PRUEBA1', $codes);
    }

    public function test_switch_tenant_updates_me_roles_without_relogin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $login->assertOk();
        $token = $login->json('data.access_token');

        $sw = $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/auth/switch-tenant', [
            'tenant_codigo' => 'PRUEBA1',
        ]);
        $sw->assertOk();
        $sw->assertJsonPath('data.tenant.codigo', 'PRUEBA1');

        $me = $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson('/api/v1/auth/me');
        $me->assertOk();
        $me->assertJsonPath('data.tenant.codigo', 'PRUEBA1');
        $roles = $me->json('data.user.roles');
        $this->assertCount(1, $roles);
        $this->assertSame('user', $roles[0]['slug']);
    }

    public function test_switch_tenant_forbidden_for_non_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $login->assertOk();
        $token = $login->json('data.access_token');

        $sw = $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/auth/switch-tenant', [
            'tenant_codigo' => 'PRUEBAS',
        ]);
        $sw->assertStatus(403);
        $this->assertSame('FORBIDDEN', $sw->json('code'));
    }

    public function test_switch_tenant_not_found_unknown_codigo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $login->assertOk();
        $token = $login->json('data.access_token');

        $sw = $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/auth/switch-tenant', [
            'tenant_codigo' => 'NOEXISTE999',
        ]);
        $sw->assertStatus(404);
        $this->assertSame('TENANT_NOT_FOUND', $sw->json('code'));
    }

    public function test_switch_same_tenant_idempotent_ok(): void
    {
        $this->seed(DatabaseSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $login->assertOk();
        $token = $login->json('data.access_token');

        $sw = $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/auth/switch-tenant', [
            'tenant_codigo' => 'DEFAULT',
        ]);
        $sw->assertOk();
        $sw->assertJsonPath('data.tenant.codigo', 'DEFAULT');
    }
}
