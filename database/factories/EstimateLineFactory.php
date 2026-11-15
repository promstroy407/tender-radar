<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LineMatch;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateLine>
 */
class EstimateLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_file_id' => EstimateFile::factory(),
            'estimate_id' => fn (array $attributes): int => EstimateFile::query()->whereKey((int) $attributes['estimate_file_id'])->value('estimate_id'),
            'position' => 1,
            'name' => 'Извещатель пожарный дымовой ИП 212-141',
            'unit' => 'шт',
            'quantity_milli' => 10_000,
            'material_kopecks' => null,
            'price_item_id' => null,
            'match' => LineMatch::Auto,
        ];
    }
}
