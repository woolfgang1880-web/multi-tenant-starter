<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PASO 3 — Aislamiento multi-tenant en API (usuarios y roles).
 */
class TenantIsolationApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $user->tenant->codigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ]);
        $login->assertOk();

        return ['Authorization' => 'Bearer '.$login->json('data.access_token')];
    }

    private function seedAdminInDefault(): User
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
        $role = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin->fresh(['tenant']);
    }

    private function otherTenantWithUserAndRole(): array
    {
        $other = Tenant::factory()->create(['codigo' => 'OTHER', 'nombre' => 'Otra', 'slug' => 'otra']);
        $foreignUser = User::factory()->create([
            'tenant_id' => $other->id,
            'usuario' => 'victim',
            'password_hash' => 'password',
        ]);
        $foreignRole = Role::query()->create([
            'tenant_id' => $other->id,
            'nombre' => 'Extranjero',
            'slug' => 'extranjero',
            'descripcion' => null,
        ]);

        return ['tenant' => $other, 'user' => $foreignUser, 'role' => $foreignRole];
    }

    public function test_cannot_show_user_from_other_tenant_returns_404(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreign = $this->otherTenantWithUserAndRole()['user'];
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->getJson('/api/v1/users/'.$foreign->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_cannot_show_role_from_other_tenant_returns_404(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreignRole = $this->otherTenantWithUserAndRole()['role'];
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->getJson('/api/v1/roles/'.$foreignRole->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_list_users_never_includes_other_tenant_despite_query_params(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreign = $this->otherTenantWithUserAndRole()['user'];
        $h = $this->authHeader($admin);

        $res = $this->withHeaders($h)->getJson('/api/v1/users?per_page=100&tenant_id='.$foreign->tenant_id.'&page=1');
        $res->assertOk();
        $ids = collect($res->json('data.items'))->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_list_roles_never_includes_other_tenant_despite_query_params(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreignRole = $this->otherTenantWithUserAndRole()['role'];
        $h = $this->authHeader($admin);

        $res = $this->withHeaders($h)->getJson('/api/v1/roles?per_page=100&tenant_id='.$foreignRole->tenant_id);
        $res->assertOk();
        $ids = collect($res->json('data.items'))->pluck('id')->all();
        $this->assertNotContains($foreignRole->id, $ids);
    }

    public function test_cannot_attach_role_from_other_tenant_returns_422(): void
    {
        $admin = $this->seedAdminInDefault();
        ['user' => $foreignUser, 'role' => $foreignRole] = $this->otherTenantWithUserAndRole();

        $localUser = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'usuario' => 'localop',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $h = $this->authHeader($admin);

        $this->withHeaders($h)->postJson('/api/v1/users/'.$localUser->id.'/roles', [
            'role_ids' => [$foreignRole->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_cannot_sync_role_from_other_tenant_returns_422(): void
    {
        $admin = $this->seedAdminInDefault();
        $localRole = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();
        $foreignRole = $this->otherTenantWithUserAndRole()['role'];

        $localUser = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'usuario' => 'syncuser',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $h = $this->authHeader($admin);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$localUser->id.'/roles', [
            'role_ids' => [$localRole->id, $foreignRole->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_cannot_update_user_from_other_tenant_returns_404(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreign = $this->otherTenantWithUserAndRole()['user'];
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$foreign->id, [
            'usuario' => 'hacked',
            'codigo_cliente' => 'X',
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_cannot_update_role_from_other_tenant_returns_404(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreignRole = $this->otherTenantWithUserAndRole()['role'];
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->putJson('/api/v1/roles/'.$foreignRole->id, [
            'nombre' => 'Hacked',
            'slug' => 'hacked',
            'descripcion' => null,
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_cannot_deactivate_user_from_other_tenant_returns_404(): void
    {
        $admin = $this->seedAdminInDefault();
        $foreign = $this->otherTenantWithUserAndRole()['user'];
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->patchJson('/api/v1/users/'.$foreign->id.'/deactivate')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_cannot_assign_roles_to_user_from_other_tenant_returns_404(): void
    {
        $admin = $this->seedAdminInDefault();
        $localRole = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();
        $foreign = $this->otherTenantWithUserAndRole()['user'];
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$foreign->id.'/roles', [
            'role_ids' => [$localRole->id],
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }
}
