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

class SecurityCriticalTest extends TestCase
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

    private function seedAdmin(): User
    {
        $this->seed(TenantSeeder::class);
        $this->seed(RoleSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->firstOrFail();

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'admin',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        return $admin->fresh(['roles']);
    }

    private function seedBasicUser(): User
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

    // ─── 1. REFRESH TOKEN (PASO 2: rotación y detección de reuse) ──────────────

    public function test_refresh_token_rotates_correctly(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $login->assertOk();

        $refresh1 = $login->json('data.refresh_token');
        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh1]);
        $r1->assertOk();
        $refresh2 = $r1->json('data.refresh_token');
        $access2 = $r1->json('data.access_token');

        $this->withToken($access2)->getJson('/api/v1/auth/me')->assertOk();

        $r2 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh2]);
        $r2->assertOk();
        $refresh3 = $r2->json('data.refresh_token');
        $access3 = $r2->json('data.access_token');
        $this->withToken($access3)->getJson('/api/v1/auth/me')->assertOk();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh1])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh2])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);
    }

    public function test_refresh_token_cannot_be_reused(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $login->assertOk();

        $refresh = $login->json('data.refresh_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r1->assertOk();

        $r2 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r2->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);
    }

    public function test_refresh_token_reuse_returns_invalid(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);

        $refresh = $login->json('data.refresh_token');
        $access1 = $login->json('data.access_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r1->assertOk();
        $access2 = $r1->json('data.access_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);

        // Tras reuse detectado, la sesión completa se invalida: access2 deja de funcionar
        $this->withToken($access2)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withToken($access1)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    // ─── 2. SESIÓN: doble refresh (secuencial simula concurrencia) ──────────────

    public function test_rapid_double_refresh_second_fails(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);

        $refresh = $login->json('data.refresh_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r2 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);

        $r1->assertOk();
        $r2->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);
    }

    public function test_reusing_refresh_token_revokes_session(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $refresh1 = $login->json('data.refresh_token');
        $access1 = $login->json('data.access_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh1])->assertOk();
        $refresh2 = $r1->json('data.refresh_token');
        $access2 = $r1->json('data.access_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh1])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);

        $this->withToken($access1)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withToken($access2)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_revoked_session_cannot_refresh(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $refresh1 = $login->json('data.refresh_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh1])->assertOk();
        $refresh2 = $r1->json('data.refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh1])->assertStatus(401);

        // Tras reuse, la sesión se invalida y refresh2 queda revocado → REFRESH_INVALID
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh2])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);
    }

    public function test_only_one_concurrent_refresh_succeeds(): void
    {
        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $refresh = $login->json('data.refresh_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        $r2 = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh]);

        $successCount = ($r1->getStatusCode() === 200 ? 1 : 0) + ($r2->getStatusCode() === 200 ? 1 : 0);
        $this->assertSame(1, $successCount);
        $this->assertTrue($r1->isSuccessful() xor $r2->isSuccessful());
    }

    // ─── 3. MULTI-TENANT: IDOR ────────────────────────────────────────────────

    public function test_idor_cannot_update_user_from_other_tenant(): void
    {
        $admin = $this->seedAdmin();

        $otherTenant = Tenant::factory()->create(['codigo' => 'OTHER', 'nombre' => 'Otra', 'slug' => 'otra']);
        $foreign = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'usuario' => 'victim',
            'password_hash' => 'password',
        ]);

        $h = $this->authHeader($admin);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$foreign->id, [
            'usuario' => 'hacked',
            'codigo_cliente' => 'X',
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_idor_cannot_deactivate_user_from_other_tenant(): void
    {
        $admin = $this->seedAdmin();

        $otherTenant = Tenant::factory()->create(['codigo' => 'OTHER', 'nombre' => 'Otra', 'slug' => 'otra']);
        $foreign = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'usuario' => 'victim',
            'password_hash' => 'password',
        ]);

        $h = $this->authHeader($admin);

        $this->withHeaders($h)->patchJson('/api/v1/users/'.$foreign->id.'/deactivate')
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_idor_cannot_assign_roles_to_user_from_other_tenant(): void
    {
        $admin = $this->seedAdmin();
        $role = Role::query()->where('tenant_id', $admin->tenant_id)->where('slug', 'user')->firstOrFail();

        $otherTenant = Tenant::factory()->create(['codigo' => 'OTHER', 'nombre' => 'Otra', 'slug' => 'otra']);
        $foreign = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'usuario' => 'victim',
            'password_hash' => 'password',
        ]);

        $h = $this->authHeader($admin);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$foreign->id.'/roles', [
            'role_ids' => [$role->id],
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    // ─── 4. ROLES: escalación de privilegios ───────────────────────────────────

    public function test_basic_user_cannot_assign_roles_to_self_or_others(): void
    {
        $basic = $this->seedBasicUser();
        $adminRole = Role::query()->where('tenant_id', $basic->tenant_id)->where('slug', 'admin')->firstOrFail();

        $h = $this->authHeader($basic);

        $this->withHeaders($h)->putJson('/api/v1/users/'.$basic->id.'/roles', [
            'role_ids' => [$adminRole->id],
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::FORBIDDEN);
    }

    public function test_basic_user_cannot_create_roles(): void
    {
        $basic = $this->seedBasicUser();
        $h = $this->authHeader($basic);

        $this->withHeaders($h)->postJson('/api/v1/roles', [
            'nombre' => 'Pirate',
            'slug' => 'pirate',
            'descripcion' => 'Escalación',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::FORBIDDEN);
    }

    // ─── 5. USUARIOS ──────────────────────────────────────────────────────────

    public function test_duplicate_usuario_in_same_tenant_returns_422(): void
    {
        $admin = $this->seedAdmin();
        $h = $this->authHeader($admin);

        $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'duplicado',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'codigo_cliente' => 'C-1',
        ])->assertCreated();

        $res = $this->withHeaders($h)->postJson('/api/v1/users', [
            'usuario' => 'duplicado',
            'password' => 'password456',
            'password_confirmation' => 'password456',
            'codigo_cliente' => 'C-2',
        ]);

        $res->assertStatus(422);
        $this->assertArrayHasKey('usuario', $res->json('data.errors') ?? []);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'inactivo',
            'password_hash' => 'password',
            'activo' => false,
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'inactivo',
            'password' => 'password',
        ]);

        $res->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::ACCOUNT_INACTIVE);
    }

    // ─── 6. LOGS ───────────────────────────────────────────────────────────────
    // Verifica que los eventos de seguridad se emiten (leyendo el canal security).
    // Canal 'security' usa driver 'daily' -> security-YYYY-MM-DD.log

    private function securityLogPath(): string
    {
        return storage_path('logs/security-'.now()->format('Y-m-d').'.log');
    }

    public function test_security_log_on_login_failed(): void
    {
        $logPath = $this->securityLogPath();
        if (file_exists($logPath)) {
            @unlink($logPath);
        }

        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'admin',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'wrong',
        ])->assertStatus(401);

        $this->assertFileExists($logPath);
        $content = file_get_contents($logPath);
        $this->assertStringContainsString('auth.login.failed', $content);
        $this->assertStringContainsString('bad_credentials', $content);
        $this->assertStringContainsString('"type":"auth.login.failed"', $content);
        $this->assertStringContainsString('"severity":"warning"', $content);
        $this->assertStringContainsString('"actor_user_id":null', $content);
        $this->assertStringContainsString('"ip":"127.0.0.1"', $content);
        $this->assertStringContainsString('"metadata":{"reason":"bad_credentials"', $content);
        $this->assertStringNotContainsString('"password":"wrong"', $content);
    }

    public function test_security_event_logged_on_token_reuse(): void
    {
        $logPath = $this->securityLogPath();
        if (file_exists($logPath)) {
            @unlink($logPath);
        }

        $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ]);
        $refresh = $login->json('data.refresh_token');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])->assertOk();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refresh])->assertStatus(401);

        $this->assertFileExists($logPath);
        $content = file_get_contents($logPath);
        $this->assertStringContainsString('auth.refresh.reuse_detected', $content);
        $this->assertStringContainsString('high', $content);
        $this->assertStringContainsString('"type":"auth.refresh.reuse_detected"', $content);
        $this->assertStringContainsString('"severity":"high"', $content);
        $this->assertStringContainsString('"session_id"', $content);
        $this->assertStringNotContainsString($refresh, $content);
    }
}
