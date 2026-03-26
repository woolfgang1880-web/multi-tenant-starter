<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupDemoCommand extends Command
{
    /**
     * Estado base reproducible: migraciones seguras + seeders idempotentes.
     * No ejecuta migrate:fresh ni elimina datos.
     */
    protected $signature = 'app:setup-demo
        {--no-migrate : Solo ejecutar seeders (sin aplicar migraciones pendientes)}';

    protected $description = 'Aplica migraciones pendientes (migrate, sin fresh) y seeders demo idempotentes.';

    public function handle(): int
    {
        if (! $this->option('no-migrate')) {
            $this->info('Aplicando migraciones pendientes (`migrate`, no borra tablas ni datos existentes).');
            $exitCode = Artisan::call('migrate', ['--force' => true], $this->output);
            if ($exitCode !== 0) {
                $this->error('migrate falló (código '.$exitCode.').');

                return self::FAILURE;
            }
            $this->newLine();
        } else {
            $this->comment('Sin migraciones (--no-migrate): solo seeders.');
        }

        $this->info('Ejecutando DatabaseSeeder (TenantSeeder, RoleSeeder, DemoUserSeeder).');
        $exitCode = Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ], $this->output);

        if ($exitCode !== 0) {
            $this->error('db:seed falló (código '.$exitCode.').');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Listo. Datos demo asegurados de forma idempotente (sin destruir datos existentes).');
        $this->line('Documentación: docs/SETUP_DEMO.md · docs/DEMO_USERS.md');

        return self::SUCCESS;
    }
}
