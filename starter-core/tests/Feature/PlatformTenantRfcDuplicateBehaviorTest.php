<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTenantRfcDuplicateBehaviorTest extends TestCase
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

    private function platformAdminHeader(): array
    {
        $this->seed(TenantSeeder::class);
        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin_rfc',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        return $this->authHeader($super, 'DEFAULT');
    }

    private function basePayload(string $nombre, string $codigo): array
    {
        return [
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'AAA010101AAA',
            'nombre_fiscal' => 'Empresa Fiscal',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ];
    }

    public function test_allows_same_rfc_on_different_tenants(): void
    {
        $h = $this->platformAdminHeader();

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', $this->basePayload('Empresa 1', 'RFC1'))
            ->assertCreated()
            ->assertJsonPath('code', 'OK');

        $first = Tenant::query()->where('codigo', 'RFC1')->firstOrFail();
        $first->forceFill([
            'operational_status' => Tenant::OPERATIONAL_INACTIVE,
            'inactivated_at' => now(),
        ])->save();

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', $this->basePayload('Empresa 2', 'RFC2'))
            ->assertCreated()
            ->assertJsonPath('code', 'OK');

        $this->assertSame(2, Tenant::query()->where('rfc', 'AAA010101AAA')->count());
    }

    public function test_rejects_generic_rfc(): void
    {
        $h = $this->platformAdminHeader();

        $payload = $this->basePayload('Gen', 'GEN1');
        $payload['rfc'] = 'XAXX010101000';

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
