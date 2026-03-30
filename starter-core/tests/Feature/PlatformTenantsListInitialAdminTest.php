<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTenantsListInitialAdminTest extends TestCase
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

    public function test_list_includes_initial_admin_after_creating_tenant_admin(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin_list',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Tenant List',
            'codigo' => 'TLIST',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TLS010101AA1',
            'nombre_fiscal' => 'TENANT LIST SA DE CV',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ])->assertCreated();

        $before = $this->withHeaders($h)->getJson('/api/v1/platform/tenants');
        $before->assertOk();
        $itemBefore = collect($before->json('data.items'))->firstWhere('codigo', 'TLIST');
        $this->assertNotNull($itemBefore);
        $this->assertNull($itemBefore['initial_admin'] ?? null);
        $this->assertSame('01010', $itemBefore['codigo_postal'] ?? null);

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants/TLIST/admins', [
            'admin_usuario' => 'admin_tlist',
            'admin_password' => 'Admin1234!',
            'admin_password_confirmation' => 'Admin1234!',
            'admin_codigo_cliente' => 'CLI-TL',
        ])->assertCreated();

        $after = $this->withHeaders($h)->getJson('/api/v1/platform/tenants');
        $after->assertOk();
        $itemAfter = collect($after->json('data.items'))->firstWhere('codigo', 'TLIST');
        $this->assertNotNull($itemAfter);
        $this->assertIsArray($itemAfter['initial_admin']);
        $this->assertSame('admin_tlist', $itemAfter['initial_admin']['usuario']);
        $this->assertSame('CLI-TL', $itemAfter['initial_admin']['codigo_cliente']);
        $this->assertTrue($itemAfter['initial_admin']['activo']);
        $this->assertSame('01010', $itemAfter['codigo_postal'] ?? null);
    }
}
