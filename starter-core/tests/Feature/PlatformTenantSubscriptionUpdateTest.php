<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTenantSubscriptionUpdateTest extends TestCase
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

    public function test_platform_admin_can_set_trial_tenant_to_active(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $trialTenant = Tenant::factory()->create([
            'codigo' => 'TRIALX',
            'nombre' => 'Trial X',
            'activo' => true,
            'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
            'trial_starts_at' => now()->subDay(),
            'trial_ends_at' => now()->addWeek(),
        ]);

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin_sub',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $res = $this->withHeaders($h)->patchJson('/api/v1/platform/tenants/TRIALX/subscription', [
            'subscription_status' => 'active',
        ]);

        $res->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('data.subscription_status', 'active');

        $this->assertSame(
            Tenant::SUBSCRIPTION_ACTIVE,
            Tenant::query()->where('codigo', 'TRIALX')->value('subscription_status')
        );
    }

    public function test_patch_fails_when_tenant_not_in_trial(): void
    {
        $this->seed(TenantSeeder::class);

        $default = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();

        $super = User::factory()->create([
            'tenant_id' => $default->id,
            'usuario' => 'platform_admin_sub2',
            'password_hash' => 'password',
            'activo' => true,
            'is_platform_admin' => true,
        ]);

        $h = $this->authHeader($super, 'DEFAULT');

        $res = $this->withHeaders($h)->patchJson('/api/v1/platform/tenants/DEFAULT/subscription', [
            'subscription_status' => 'active',
        ]);

        $res->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
