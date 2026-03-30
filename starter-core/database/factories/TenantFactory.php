<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('TEN-####'),
            'nombre' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'activo' => true,
            'operational_status' => Tenant::OPERATIONAL_ACTIVE,
        ];
    }
}
