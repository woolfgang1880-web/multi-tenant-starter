<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Estabilidad del login clásico (tenant_codigo + usuario + password) tras Fase 1
 * (user_tenants + tenant activo por sesión).
 */
class LoginEndToEndStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_ok_when_only_users_tenant_id_matches_and_pivot_row_missing(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $user = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'legacy_no_pivot',
            'password_hash' => 'secret',
            'activo' => true,
        ]);
        DB::table('user_tenants')->where('user_id', $user->id)->delete();

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'legacy_no_pivot',
            'password' => 'secret',
        ]);

        $res->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'session_uuid']]);
    }

    public function test_demo_admin_pruebas_and_multi_demo_login_and_me_tenant_matches_login(): void
    {
        $this->seed(DatabaseSeeder::class);

        $multi = User::query()->where('usuario', 'multi_demo')->firstOrFail();
        $this->assertTrue($multi->tenants()->where('tenants.codigo', 'DEFAULT')->exists());
        $this->assertTrue($multi->tenants()->where('tenants.codigo', 'PRUEBA1')->exists());

        $cases = [
            ['tenant_codigo' => 'DEFAULT', 'usuario' => 'admin_demo', 'password' => 'Admin123!', 'expected_tenant' => 'DEFAULT', 'expected_role' => 'admin'],
            ['tenant_codigo' => 'PRUEBAS', 'usuario' => 'admin_pruebas', 'password' => 'AdminPruebas123!', 'expected_tenant' => 'PRUEBAS', 'expected_role' => 'admin'],
            ['tenant_codigo' => 'DEFAULT', 'usuario' => 'multi_demo', 'password' => 'MultiDemo123!', 'expected_tenant' => 'DEFAULT', 'expected_role' => 'admin'],
            ['tenant_codigo' => 'PRUEBA1', 'usuario' => 'multi_demo', 'password' => 'MultiDemo123!', 'expected_tenant' => 'PRUEBA1', 'expected_role' => 'user'],
        ];

        foreach ($cases as $case) {
            $login = $this->postJson('/api/v1/auth/login', [
                'tenant_codigo' => $case['tenant_codigo'],
                'usuario' => $case['usuario'],
                'password' => $case['password'],
            ]);
            $login->assertOk()->assertJsonPath('code', 'OK');
            $token = $login->json('data.access_token');
            $this->assertIsString($token);

            $me = $this->withHeaders(['Authorization' => 'Bearer '.$token])
                ->getJson('/api/v1/auth/me');
            $me->assertOk()
                ->assertJsonPath('data.tenant.codigo', $case['expected_tenant'])
                ->assertJsonPath('data.user.usuario', $case['usuario']);

            $roles = $me->json('data.user.roles');
            $this->assertIsArray($roles);
            $this->assertCount(1, $roles);
            $this->assertSame($case['expected_role'], $roles[0]['slug']);

            $sessionUuid = $login->json('data.session_uuid');
            $this->assertIsString($sessionUuid);
            $sessionRow = DB::table('user_sessions')->where('session_uuid', $sessionUuid)->first();
            $this->assertNotNull($sessionRow, 'La sesión debe existir tras login OK');
            $this->assertNotNull(
                $sessionRow->tenant_id,
                'user_sessions.tenant_id debe estar presente (migraciones 2026_03_26 o ensure 2026_03_27 aplicadas)'
            );
        }
    }
}
