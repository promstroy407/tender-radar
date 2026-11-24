<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EstimateFile;
use App\Models\EstimateMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateMaterial>
 */
class EstimateMaterialFactory extends Factory
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
            'code' => '21.1.08.03-0573',
            'name' => 'Лоток перфорированный 200х50 мм',
            'unit' => 'м',
            'quantity_milli' => 10_000,
            'total_kopecks' => 1_234_500,
        ];
    }
}
