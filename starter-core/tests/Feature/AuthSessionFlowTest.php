<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthErrorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        $this->seed(\Database\Seeders\TenantSeeder::class);

        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'admin',
            'password_hash' => 'secret',
            'activo' => true,
        ]);
    }

    public function test_login_me_logout_and_me_denied(): void
    {
        $this->seedUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'secret',
        ]);

        $login->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in', 'session_uuid']]);

        $token = $login->json('data.access_token');

        $me = $this->withToken($token)->getJson('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('data.user.usuario', 'admin');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::TOKEN_INVALID_OR_REVOKED);
    }

    public function test_second_login_supersedes_first_access_token(): void
    {
        $this->seedUser();

        $first = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'secret',
        ]);

        $token1 = $first->json('data.access_token');

        $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'secret',
        ])->assertOk();

        $this->withToken($token1)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::SESSION_SUPERSEDED);
    }

    public function test_refresh_rotates_tokens_and_old_refresh_fails(): void
    {
        $this->seedUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'secret',
        ]);

        $refresh = $login->json('data.refresh_token');

        $r1 = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refresh,
        ]);

        $r1->assertOk()->assertJsonPath('code', 'OK');

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refresh,
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::REFRESH_INVALID);
    }

    public function test_invalid_credentials_same_shape(): void
    {
        $this->seedUser();

        $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'wrong',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::INVALID_CREDENTIALS);
    }

    public function test_auth_me_without_token_returns_401(): void
    {
        $this->seedUser();

        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::UNAUTHENTICATED);
    }

    public function test_auth_me_with_invalid_token_returns_401(): void
    {
        $this->seedUser();

        $this->withToken('invalid-token-not-valid')->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::TOKEN_INVALID_OR_REVOKED);
    }

    public function test_session_invalid_when_session_row_missing(): void
    {
        $user = $this->seedUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'secret',
        ]);

        $login->assertOk();
        $token = $login->json('data.access_token');
        $sessionUuid = $login->json('data.session_uuid');

        \App\Models\UserSession::query()
            ->where('session_uuid', $sessionUuid)
            ->where('user_id', $user->id)
            ->delete();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::SESSION_INVALID);
    }

    public function test_session_expired_returns_401(): void
    {
        $this->seedUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'secret',
        ]);

        $login->assertOk();
        $token = $login->json('data.access_token');
        $sessionUuid = $login->json('data.session_uuid');

        \App\Models\UserSession::query()
            ->where('session_uuid', $sessionUuid)
            ->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', AuthErrorCode::SESSION_EXPIRED);
    }
}
