<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PriceSection;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceItem>
 */
class PriceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section' => PriceSection::Fire,
            'name' => 'Монтаж извещателя '.fake()->unique()->numberBetween(1, 100000),
            'unit' => 'шт',
            'work_price_kopecks' => 30_000,
            'keywords' => 'извещател дым',
            'is_active' => true,
        ];
    }
}
