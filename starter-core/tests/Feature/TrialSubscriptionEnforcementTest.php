<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\AuthErrorCode;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialSubscriptionEnforcementTest extends TestCase
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

    public function test_login_returns_403_when_trial_period_ended(): void
    {
        $tenant = Tenant::factory()->create([
            'codigo' => 'TRIALOFF',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
            'trial_starts_at' => now()->subMonth(),
            'trial_ends_at' => now()->subDay(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'user_trial_off',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $user->tenants()->syncWithoutDetaching([$tenant->id]);

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'TRIALOFF',
            'usuario' => 'user_trial_off',
            'password' => 'password',
        ]);

        $res->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::SUBSCRIPTION_EXPIRED);
    }

    public function test_login_ok_when_trial_still_valid(): void
    {
        $tenant = Tenant::factory()->create([
            'codigo' => 'TRIALON',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
            'trial_starts_at' => now()->subDay(),
            'trial_ends_at' => now()->addWeek(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'user_trial_on',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $user->tenants()->syncWithoutDetaching([$tenant->id]);

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'TRIALON',
            'usuario' => 'user_trial_on',
            'password' => 'password',
        ]);

        $res->assertOk()->assertJsonPath('code', 'OK');
    }

    public function test_refresh_returns_403_when_tenant_becomes_blocked_after_login(): void
    {
        $tenant = Tenant::factory()->create([
            'codigo' => 'REFBLK',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
            'trial_starts_at' => now()->subDay(),
            'trial_ends_at' => now()->addWeek(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'user_ref_blk',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $user->tenants()->syncWithoutDetaching([$tenant->id]);

        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'REFBLK',
            'usuario' => 'user_ref_blk',
            'password' => 'password',
        ]);
        $login->assertOk();
        $refresh = $login->json('data.refresh_token');

        $tenant->forceFill([
            'trial_ends_at' => now()->subHour(),
        ])->save();

        $refRes = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refresh,
        ]);

        $refRes->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::SUBSCRIPTION_EXPIRED);
    }

    public function test_switch_tenant_blocked_when_target_subscription_blocked(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $blocked = Tenant::factory()->create([
            'codigo' => 'BLKSW',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_EXPIRED,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'switch_blk',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $user->tenants()->syncWithoutDetaching([$default->id, $blocked->id]);

        $h = $this->authHeader($user, 'DEFAULT');

        $switch = $this->withHeaders($h)->postJson('/api/v1/auth/switch-tenant', [
            'tenant_codigo' => 'BLKSW',
        ]);

        $switch->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::SUBSCRIPTION_EXPIRED);
    }

    public function test_me_returns_403_when_tenant_becomes_blocked_after_login(): void
    {
        $tenant = Tenant::factory()->create([
            'codigo' => 'MEBLK',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'user_me_blk',
            'password_hash' => 'password',
            'activo' => true,
        ]);
        $user->tenants()->syncWithoutDetaching([$tenant->id]);

        $h = $this->authHeader($user, 'MEBLK');

        $tenant->forceFill([
            'subscription_status' => Tenant::SUBSCRIPTION_SUSPENDED,
        ])->save();

        $me = $this->withHeaders($h)->getJson('/api/v1/auth/me');

        $me->assertStatus(403)
            ->assertJsonPath('code', AuthErrorCode::SUBSCRIPTION_EXPIRED);
    }
}
