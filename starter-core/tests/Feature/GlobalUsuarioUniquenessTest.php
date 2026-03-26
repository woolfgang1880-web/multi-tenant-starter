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
 * Fase 1: `usuario` es único en toda la plataforma (no solo por tenant).
 */
class GlobalUsuarioUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function bearerFor(User $user): array
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => $user->tenant->codigo,
            'usuario' => $user->usuario,
            'password' => 'password',
        ]);
        $login->assertOk();

        return ['Authorization' => 'Bearer '.$login->json('data.access_token')];
    }

    private function seedAdminInTenant(Tenant $tenant, string $usuario): User
    {
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => $usuario,
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $role = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return $admin->fresh(['tenant', 'roles']);
    }

    public function test_no_puede_crear_usuario_con_usuario_ya_existente_en_otro_tenant(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $prueba1 = Tenant::query()->where('codigo', 'PRUEBA1')->firstOrFail();

        $adminDefault = $this->seedAdminInTenant($default, 'owner_default');
        $adminPrueba1 = $this->seedAdminInTenant($prueba1, 'owner_prueba1');

        $h1 = $this->bearerFor($adminDefault);
        $this->withHeaders($h1)->postJson('/api/v1/users', [
            'usuario' => 'login_global_unico',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $h2 = $this->bearerFor($adminPrueba1);
        $dup = $this->withHeaders($h2)->postJson('/api/v1/users', [
            'usuario' => 'login_global_unico',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $dup->assertStatus(422);
        $this->assertArrayHasKey('usuario', $dup->json('data.errors') ?? []);
    }

    public function test_puede_actualizar_usuario_conservando_mismo_usuario(): void
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $admin = $this->seedAdminInTenant($tenant, 'admin_self');
        $h = $this->bearerFor($admin);

        $create = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'operador_x',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->withHeaders($h)->putJson('/api/v1/users/'.$id, [
            'usuario' => 'operador_x',
            'codigo_cliente' => 'C-99',
        ])->assertOk()->assertJsonPath('data.codigo_cliente', 'C-99');
    }
}
