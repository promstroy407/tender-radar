<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inn' => fake()->unique()->numerify('##########'),
            'kpp' => fake()->numerify('#########'),
            'name' => 'ООО «'.fake()->unique()->company().'»',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+7 9## ###-##-##'),
            'website' => fake()->domainName(),
            'director_name' => fake()->name(),
            'city' => fake()->city(),
        ];
    }
}
