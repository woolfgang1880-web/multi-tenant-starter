<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $profile = env('SEED_PROFILE', 'full');

        if ($profile === 'minimal') {
            $this->call([
                MinimalDevSeeder::class,
            ]);

            return;
        }

        $this->call([
            TenantSeeder::class,
            RoleSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
