<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestCorrelationTest extends TestCase
{
    use RefreshDatabase;

    private function securityLogPath(): string
    {
        return storage_path('logs/security-'.now()->format('Y-m-d').'.log');
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

        return $admin->fresh(['tenant']);
    }

    public function test_response_contains_request_id_and_trace_id_headers(): void
    {
        $res = $this->getJson('/api/v1/health');
        $res->assertOk();
        $res->assertHeader('X-Request-Id');
        $res->assertHeader('X-Trace-Id');

        $this->assertNotEmpty($res->headers->get('X-Request-Id'));
        $this->assertNotEmpty($res->headers->get('X-Trace-Id'));
    }

    public function test_request_id_is_consistent_between_response_header_and_security_log(): void
    {
        $logPath = $this->securityLogPath();
        if (file_exists($logPath)) {
            @unlink($logPath);
        }

        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('codigo', 'DEFAULT')->firstOrFail();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'usuario' => 'badlogin',
            'password_hash' => 'password',
            'activo' => true,
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'badlogin',
            'password' => 'wrong',
        ]);
        $res->assertStatus(401);

        $requestId = (string) $res->headers->get('X-Request-Id');
        $traceId = (string) $res->headers->get('X-Trace-Id');
        $this->assertNotSame('', $requestId);
        $this->assertNotSame('', $traceId);

        $this->assertFileExists($logPath);
        $content = (string) file_get_contents($logPath);
        $this->assertStringContainsString('"request_id":"'.$requestId.'"', $content);
        $this->assertStringContainsString('"trace_id":"'.$traceId.'"', $content);
    }

    public function test_provided_request_and_trace_ids_are_propagated(): void
    {
        $admin = $this->seedAdmin();
        $login = $this->postJson('/api/v1/auth/login', [
            'tenant_codigo' => 'DEFAULT',
            'usuario' => 'admin',
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('data.access_token');

        $requestId = 'req-test-123';
        $traceId = 'trace-test-abc';

        $res = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Request-Id' => $requestId,
            'X-Trace-Id' => $traceId,
        ])->getJson('/api/v1/auth/me');

        $res->assertOk();
        $res->assertHeader('X-Request-Id', $requestId);
        $res->assertHeader('X-Trace-Id', $traceId);
    }
}

