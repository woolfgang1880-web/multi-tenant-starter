<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 (membresía N:N): mismo usuario global puede entrar a varias empresas con tenant_codigo;
 * roles y /me respetan el tenant de la sesión.
 */
class MultiTenantMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_demo_login_default_and_prueba1_resolve_roles_per_session_tenant(): void
    {
        $this->seed(DatabaseSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $prueba1 = Tenant::query()->where('codigo', 'PRUEBA1')->firstOrFail();

        $multi = User::query()->where('usuario', 'multi_demo')->firstOrFail();
        $this->assertTrue($multi->belongsToTenantId($default->id));
        $this->assertTrue($multi->belongsToTenantId($prueba1->id));

        $loginDefault = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $loginDefault->assertOk();
        $tokenD = $loginDefault->json('data.access_token');

        $meD = $this->withHeaders(['Authorization' => 'Bearer '.$tokenD])->getJson('/api/v1/auth/me');
        $meD->assertOk();
        $meD->assertJsonPath('data.tenant.codigo', 'DEFAULT');
        $rolesD = $meD->json('data.user.roles');
        $this->assertCount(1, $rolesD);
        $this->assertSame('admin', $rolesD[0]['slug']);

        $loginP1 = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'PRUEBA1',
            'usuario' => 'multi_demo',
            'password' => 'MultiDemo123!',
        ]);
        $loginP1->assertOk();
        $tokenP = $loginP1->json('data.access_token');

        $meP = $this->withHeaders(['Authorization' => 'Bearer '.$tokenP])->getJson('/api/v1/auth/me');
        $meP->assertOk();
        $meP->assertJsonPath('data.tenant.codigo', 'PRUEBA1');
        $rolesP = $meP->json('data.user.roles');
        $this->assertCount(1, $rolesP);
        $this->assertSame('user', $rolesP[0]['slug']);
    }

    public function test_login_fails_when_user_not_member_of_tenant(): void
    {
        $this->seed(DatabaseSeeder::class);

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'PRUEBAS',
            'usuario' => 'admin_demo',
            'password' => 'Admin123!',
        ]);

        $res->assertStatus(401);
    }
}
