<?php

namespace App\Console\Commands;

use Database\Seeders\MinimalDevSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class ResetDevDatabaseCommand extends Command
{
    protected $signature = 'app:reset-dev';

    protected $description = 'Solo APP_ENV=local: migrate:fresh y seed mínimo (MinimalDevSeeder).';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('app:reset-dev solo está permitido con APP_ENV=local.');

            return self::FAILURE;
        }

        $this->warn('Se ejecutará migrate:fresh (destruye datos) y MinimalDevSeeder.');

        $code = Artisan::call('migrate:fresh', ['--force' => true], $this->output);
        if ($code !== 0) {
            return self::FAILURE;
        }

        $code = Artisan::call('db:seed', [
            '--class' => MinimalDevSeeder::class,
            '--force' => true,
        ], $this->output);

        if ($code !== 0) {
            return self::FAILURE;
        }

        $this->info('Listo. Credenciales mínimas: ver MinimalDevSeeder (platform_demo_min / PlatformMin123!).');

        return self::SUCCESS;
    }
}
