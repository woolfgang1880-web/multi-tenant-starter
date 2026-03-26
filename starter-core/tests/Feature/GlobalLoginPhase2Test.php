<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalLoginPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_global_login_single_tenant_returns_ok_and_tokens(): void
    {
        $this->seed(DatabaseSeeder::class);

        $res = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'admin_demo',
            'password' => 'Admin123!',
        ]);

        $res->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'session_uuid'],
            ]);
    }

    public function test_global_login_multi_tenant_returns_selection_payload(): void
    {
        $this->seed(DatabaseSeeder::class);

        $res = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);

        $res->assertOk()
            ->assertJsonPath('code', 'TENANT_SELECTION_REQUIRED')
            ->assertJsonPath('message', 'Seleccione empresa para continuar.')
            ->assertJsonStructure([
                'data' => [
                    'selection_token',
                    'expires_in',
                    'tenants' => [
                        ['id', 'codigo', 'nombre', 'slug'],
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(2, count($res->json('data.tenants')));
    }

    public function test_select_tenant_exchanges_token_for_session_tokens(): void
    {
        $this->seed(DatabaseSeeder::class);

        $step1 = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $plain = $step1->json('data.selection_token');
        $this->assertIsString($plain);

        $step2 = $this->postJson('/api/v1/auth/login/select-tenant', [
            'selection_token' => $plain,
            'tenant_codigo' => 'PRUEBA1',
        ]);

        $step2->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'session_uuid'],
            ]);

        $me = $this->withHeaders(['Authorization' => 'Bearer '.$step2->json('data.access_token')])
            ->getJson('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('data.tenant.codigo', 'PRUEBA1');
    }

    public function test_selection_token_is_single_use(): void
    {
        $this->seed(DatabaseSeeder::class);

        $step1 = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $plain = $step1->json('data.selection_token');

        $this->postJson('/api/v1/auth/login/select-tenant', [
            'selection_token' => $plain,
            'tenant_codigo' => 'DEFAULT',
        ])->assertOk();

        $again = $this->postJson('/api/v1/auth/login/select-tenant', [
            'selection_token' => $plain,
            'tenant_codigo' => 'PRUEBA1',
        ]);
        $again->assertStatus(401)->assertJsonPath('code', 'SELECTION_TOKEN_INVALID');
    }

    public function test_legacy_login_with_tenant_codigo_unchanged(): void
    {
        $this->seed(DatabaseSeeder::class);

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin_demo',
            'password' => 'Admin123!',
        ]);

        $res->assertOk()->assertJsonPath('code', 'OK');
    }

    public function test_global_login_invalid_password_returns_401(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'usuario' => 'multi_demo',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }
}
