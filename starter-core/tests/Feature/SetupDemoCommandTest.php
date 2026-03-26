<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_demo_runs_twice_without_errors_and_seeds_demo_data(): void
    {
        $this->artisan('app:setup-demo')->assertExitCode(0);
        $this->artisan('app:setup-demo')->assertExitCode(0);

        foreach (['DEFAULT', 'PRUEBA1', 'PRUEBAS'] as $codigo) {
            $this->assertDatabaseHas('tenants', ['codigo' => $codigo]);
        }

        $defaultId = Tenant::query()->where('codigo', 'DEFAULT')->value('id');
        $this->assertNotNull($defaultId);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $defaultId,
            'usuario' => 'admin_demo',
        ]);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $defaultId,
            'usuario' => 'user_demo',
        ]);

        $this->assertDatabaseHas('users', ['usuario' => 'admin_prueba1']);
        $this->assertDatabaseHas('users', ['usuario' => 'user_prueba1']);

        $this->assertSame(
            9,
            User::query()->whereIn('usuario', [
                'admin_demo',
                'user_demo',
                'manager_demo',
                'admin_prueba1',
                'user_prueba1',
                'multi_demo',
                'admin_pruebas',
                'user_pruebas1',
                'user_pruebas2',
            ])->count()
        );
    }

    public function test_setup_demo_no_migrate_only_seeds(): void
    {
        $this->artisan('migrate', ['--force' => true]);
        $this->artisan('app:setup-demo', ['--no-migrate' => true])->assertExitCode(0);

        $this->assertDatabaseHas('tenants', ['codigo' => 'DEFAULT']);
    }
}
