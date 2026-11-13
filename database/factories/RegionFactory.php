<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('9#'),
            'name' => 'Регион '.fake()->unique()->numberBetween(1, 1000),
            'is_enabled' => true,
            'min_amount_kopecks' => 100_000_000,
        ];
    }
}
