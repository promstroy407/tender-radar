<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstimateFileStatus;
use App\Models\Estimate;
use App\Models\EstimateFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateFile>
 */
class EstimateFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estimate_id' => Estimate::factory(),
            'original_name' => 'smeta.xlsx',
            'path' => 'estimates/test/smeta.xlsx',
            'size_bytes' => 1024,
            'status' => EstimateFileStatus::Parsed,
        ];
    }
}
