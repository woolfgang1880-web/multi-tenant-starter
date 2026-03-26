<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersAndRolesApiTest extends TestCase
{
    use RefreshDatabase;

    private function securityLogPath(): string
    {
        return storage_path('logs/security-'.now()->format('Y-m-d').'.log');
    }

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

    private function seedAdmin(): User
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

        return $admin->fresh(['roles']);
    }

    public function test_full_user_and_role_flow(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);

        $role = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();

        $create = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'operador',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-1',
        ]);

        $create->assertCreated()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('data.usuario', 'operador');

        $newId = $create->json('data.id');
        $this->assertIsInt($newId);

        $this->withHeaders($h)->getJson('/api/v1/users/'.$newId)->assertOk()
            ->assertJsonPath('data.usuario', 'operador');

        $this->withHeaders($h)->putJson('/api/v1/users/'.$newId, [
            'usuario' => 'operador',
            'codigo_cliente' => 'C-2',
        ])->assertOk()->assertJsonPath('data.codigo_cliente', 'C-2');

        $this->withHeaders($h)->postJson('/api/v1/users/'.$newId.'/roles', [
            'role_ids' => [$role->id],
        ])->assertOk()->assertJsonPath('data.roles.0.slug', 'user');

        $this->withHeaders($h)->putJson('/api/v1/users/'.$newId.'/roles', [
            'role_ids' => [],
        ])->assertOk()->assertJsonPath('data.roles', []);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$newId.'/roles', [
            'role_ids' => [$role->id],
        ])->assertOk();

        $this->withHeaders($h)->patchJson('/api/v1/users/'.$newId.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->withHeaders($h)->postJson('/api/v1/roles', [
            'nombre' => 'Auditor',
            'slug' => 'auditor',
            'descripcion' => 'Lectura',
        ])->assertCreated()->assertJsonPath('data.slug', 'auditor');
    }

    public function test_cannot_access_user_from_other_tenant(): void
    {
        $admin = $this->seedAdmin();

        $otherTenant = Tenant::factory()->create([
            'codigo' => 'OTHER',
            'nombre' => 'Otra',
            'slug' => 'otra',
        ]);

        $foreign = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'usuario' => 'extrano',
            'password_hash' => 'password',
        ]);

        $h = $this->authHeader($admin);

        $this->withHeaders($h)->getJson('/api/v1/users/'.$foreign->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_cannot_create_user_without_password(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);

        $res = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'sinpass',
            'codigo_cliente' => 'C-1',
        ]);

        $res->assertStatus(422);
        $this->assertArrayHasKey('password', $res->json('data.errors') ?? []);
    }

    public function test_cannot_create_user_with_password_too_short(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);

        $res = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'shortpass',
            'password' => 'short',
            'password_confirmation' => 'short',
            'codigo_cliente' => 'C-1',
        ]);

        $res->assertStatus(422);
        $this->assertArrayHasKey('password', $res->json('data.errors') ?? []);
    }

    public function test_admin_audit_events_have_minimum_consistent_structure(): void
    {
        $logPath = $this->securityLogPath();
        if (file_exists($logPath)) {
            @unlink($logPath);
        }

        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);
        $role = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();

        $create = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'audit_target',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'A-1',
        ])->assertCreated();

        $targetUserId = $create->json('data.id');

        $this->withHeaders($h)->putJson('/api/v1/users/'.$targetUserId.'/roles', [
            'role_ids' => [$role->id],
        ])->assertOk();

        $this->withHeaders($h)->patchJson('/api/v1/users/'.$targetUserId.'/deactivate')
            ->assertOk();

        $this->assertFileExists($logPath);
        $content = file_get_contents($logPath);

        // user.created
        $this->assertStringContainsString('admin.user.created', $content);
        $this->assertStringContainsString('"type":"admin.user.created"', $content);
        $this->assertStringContainsString('"severity":"info"', $content);
        $this->assertStringContainsString('"target_type":"user"', $content);

        // user.roles.synced
        $this->assertStringContainsString('admin.user.roles.synced', $content);
        $this->assertStringContainsString('"type":"admin.user.roles.synced"', $content);
        $this->assertStringContainsString('"metadata":{"role_count":1}', $content);

        // user.deactivated
        $this->assertStringContainsString('admin.user.deactivated', $content);
        $this->assertStringContainsString('"type":"admin.user.deactivated"', $content);
        $this->assertStringContainsString('"severity":"notice"', $content);
    }
}
