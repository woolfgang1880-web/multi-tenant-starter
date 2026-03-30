<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCompanyOperationalTest extends TestCase
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
            'usuario' => 'platform_admin_op',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        return $this->authHeader($super, 'DEFAULT');
    }

    private function tenantAdmin(): User
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'tenant_admin_co',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $role = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin->fresh(['roles']);
    }

    public function test_tenant_admin_can_patch_company_when_active(): void
    {
        $admin = $this->tenantAdmin();
        $h = $this->authHeader($admin, 'DEFAULT');

        $res = $this->withHeaders($h)->patchJson('/api/v1/tenant/company', [
            'colonia' => 'Nueva Colonia',
        ]);

        $res->assertOk()->assertJsonPath('code', 'OK');
        $this->assertSame('Nueva Colonia', Tenant::query()->where('codigo', 'DEFAULT')->value('colonia'));
    }

    public function test_cannot_patch_when_operationally_inactive(): void
    {
        $admin = $this->tenantAdmin();
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $tenant->forceFill([
            'operational_status' => Tenant::OPERATIONAL_INACTIVE,
            'inactivated_at' => now(),
        ])->save();

        $h = $this->authHeader($admin, 'DEFAULT');

        $this->withHeaders($h)->patchJson('/api/v1/tenant/company', [
            'colonia' => 'X',
        ])->assertStatus(422);
    }

    public function test_platform_inactivate_sets_operational_fields_without_touching_activo(): void
    {
        $h = $this->platformAdminHeader();

        $tenant = Tenant::factory()->create([
            'codigo' => 'OPIN',
            'activo' => true,
            'operational_status' => Tenant::OPERATIONAL_ACTIVE,
        ]);

        $actor = User::query()->where('usuario', 'platform_admin_op')->firstOrFail();

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants/OPIN/inactivate')
            ->assertOk()
            ->assertJsonPath('data.operational_status', Tenant::OPERATIONAL_INACTIVE);

        $tenant->refresh();
        $this->assertTrue($tenant->activo);
        $this->assertSame(Tenant::OPERATIONAL_INACTIVE, $tenant->operational_status);
        $this->assertNotNull($tenant->inactivated_at);
        $this->assertSame($actor->id, (int) $tenant->inactivated_by);
    }

    public function test_platform_reactivate_within_window(): void
    {
        $h = $this->platformAdminHeader();

        $tenant = Tenant::factory()->create([
            'codigo' => 'OPRE',
            'operational_status' => Tenant::OPERATIONAL_INACTIVE,
            'inactivated_at' => now()->subDays(5),
        ]);

        $actor = User::query()->where('usuario', 'platform_admin_op')->firstOrFail();

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants/OPRE/reactivate')
            ->assertOk()
            ->assertJsonPath('data.operational_status', Tenant::OPERATIONAL_ACTIVE);

        $tenant->refresh();
        $this->assertSame(Tenant::OPERATIONAL_ACTIVE, $tenant->operational_status);
        $this->assertNotNull($tenant->reactivated_at);
        $this->assertSame($actor->id, (int) $tenant->reactivated_by);
    }

    public function test_platform_cannot_reactivate_after_30_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00'));
        try {
            $h = $this->platformAdminHeader();

            Tenant::factory()->create([
                'codigo' => 'OPEX',
                'operational_status' => Tenant::OPERATIONAL_INACTIVE,
                'inactivated_at' => now()->subDays(31),
            ]);

            $this->withHeaders($h)->postJson('/api/v1/platform/tenants/OPEX/reactivate')
                ->assertStatus(422);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_immutable_fields_stripped_on_update(): void
    {
        $admin = $this->tenantAdmin();
        $h = $this->authHeader($admin, 'DEFAULT');

        $before = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $this->withHeaders($h)->patchJson('/api/v1/tenant/company', [
            'nombre' => 'Nombre Falso',
            'codigo' => 'CODIGO_FALSO',
            'rfc' => 'XXX010101XXX',
            'nombre_fiscal' => 'Fiscal Falso',
            'colonia' => 'Col OK',
        ])->assertOk();

        $after = Tenant::query()->where('id', $before->id)->firstOrFail();
        $this->assertSame($before->nombre, $after->nombre);
        $this->assertSame($before->codigo, $after->codigo);
        $this->assertSame($before->rfc, $after->rfc);
        $this->assertSame($before->nombre_fiscal, $after->nombre_fiscal);
        $this->assertSame('Col OK', $after->colonia);
    }

    public function test_user_without_role_cannot_patch_company(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $plain = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'plain_user',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $h = $this->authHeader($plain, 'DEFAULT');

        $this->withHeaders($h)->patchJson('/api/v1/tenant/company', [
            'colonia' => 'X',
        ])->assertStatus(403);
    }

    public function test_tenant_user_role_cannot_patch_company(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $userRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'user')->firstOrFail();

        $actor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'tenant_user_co',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $actor->roles()->syncWithoutDetaching([$userRole->id]);

        $h = $this->authHeader($actor, 'DEFAULT');

        $this->withHeaders($h)->patchJson('/api/v1/tenant/company', [
            'colonia' => 'NoDeberia',
        ])->assertStatus(403);
    }
}
