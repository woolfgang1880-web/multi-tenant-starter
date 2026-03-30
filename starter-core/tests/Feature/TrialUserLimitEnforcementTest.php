<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthErrorCode;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialUserLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function seedRolesForTenant(Tenant $tenant): void
    {
        $templates = [
            ['slug' => 'super_admin', 'nombre' => 'Super administrador', 'descripcion' => 't'],
            ['slug' => 'admin', 'nombre' => 'Administrador', 'descripcion' => 't'],
            ['slug' => 'user', 'nombre' => 'Usuario', 'descripcion' => 't'],
        ];
        foreach ($templates as $tpl) {
            Role::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $tpl['slug']],
                ['nombre' => $tpl['nombre'], 'descripcion' => $tpl['descripcion']]
            );
        }
    }

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

    public function test_post_users_returns_403_when_trial_and_organization_already_has_one_user(): void
    {
        $tenant = Tenant::factory()->create([
            'codigo' => 'TRIAL1',
            'nombre' => 'Trial uno',
            'slug' => 'trial1',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
            'trial_starts_at' => now()->subDay(),
            'trial_ends_at' => now()->addWeek(),
        ]);
        $this->seedRolesForTenant($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'solo_admin',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin->tenants()->syncWithoutDetaching([$tenant->id]);
        $role = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        $h = $this->authHeader($admin, 'TRIAL1');

        $res = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'segundo',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $res->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::TRIAL_USER_LIMIT_REACHED);
    }

    public function test_post_users_created_when_active_and_organization_already_has_one_user(): void
    {
        $tenant = Tenant::factory()->create([
            'codigo' => 'ACTIVE1',
            'nombre' => 'Activo',
            'slug' => 'active1',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
        ]);
        $this->seedRolesForTenant($tenant);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'admin_act',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin->tenants()->syncWithoutDetaching([$tenant->id]);
        $role = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        $h = $this->authHeader($admin, 'ACTIVE1');

        $res = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'segundo_ok',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $res->assertCreated()->assertJsonPath('code', 'OK');
    }

    public function test_platform_create_second_initial_admin_forbidden_when_trial_and_one_user_exists(): void
    {
        $this->seed(\Database\Seeders\TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin_trial',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants', [
            'nombre' => 'Solo trial',
            'codigo' => 'TRIALX',
            'activo' => true,
            'origen_datos' => 'manual',
            'tipo_contribuyente' => 'persona_moral',
            'rfc' => 'TRX010101AA1',
            'nombre_fiscal' => 'TRIAL X SA',
            'regimen_fiscal_principal' => '601',
            'codigo_postal' => '01010',
            'estado' => 'CDMX',
        ])->assertCreated();

        $this->withHeaders($h)->postJson('/api/v1/platform/tenants/TRIALX/admins', [
            'admin_usuario' => 'first_admin',
            'admin_password' => 'Admin1234!',
            'admin_password_confirmation' => 'Admin1234!',
        ])->assertCreated();

        $second = $this->withHeaders($h)->postJson('/api/v1/platform/tenants/TRIALX/admins', [
            'admin_usuario' => 'second_admin',
            'admin_password' => 'Admin1234!',
            'admin_password_confirmation' => 'Admin1234!',
        ]);

        $second->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::TRIAL_USER_LIMIT_REACHED);
    }
}
