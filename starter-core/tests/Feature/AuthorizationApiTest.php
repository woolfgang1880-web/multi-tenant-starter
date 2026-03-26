<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthErrorCode;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        $r = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $user->tenant->codigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ]);
        $r->assertOk();

        return ['Authorization' => 'Bearer '.$r->json('data.access_token')];
    }

    private function userWithOnlyBasicRole(): User
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $userRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'user')->firstOrFail();

        $u = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'basic',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $u->roles()->syncWithoutDetaching([$userRole->id]);

        return $u->fresh(['roles']);
    }

    public function test_plain_user_cannot_manage_users_or_roles(): void
    {
        $u = $this->userWithOnlyBasicRole();
        $h = $this->bearer($u);

        $this->withHeaders($h)->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::FORBIDDEN);

        $this->withHeaders($h)->getJson('/api/v1/roles')
            ->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::FORBIDDEN);
    }

    public function test_admin_user_can_access_admin_routes(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'adm2',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $h = $this->bearer($admin->fresh());

        $this->withHeaders($h)->getJson('/api/v1/users')->assertOk();
        $this->withHeaders($h)->getJson('/api/v1/roles')->assertOk();
    }
}
