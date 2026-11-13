<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContractKind;
use App\Enums\ProcurementLaw;
use App\Enums\WorkSection;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registry_number' => fake()->unique()->numerify('###################'),
            'law' => ProcurementLaw::Fz44,
            'kind' => ContractKind::Profile,
            'region_code' => Region::factory(),
            'subject' => 'Монтаж системы видеонаблюдения на объекте '.fake()->numberBetween(1, 999),
            'customer_name' => 'ГБУ «'.fake()->company().'»',
            'customer_inn' => fake()->numerify('##########'),
            'amount_kopecks' => fake()->numberBetween(1_000_000, 50_000_000) * 100,
            'signed_at' => fake()->dateTimeBetween('-7 days'),
            'ends_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'sections' => [WorkSection::Video, WorkSection::Skud],
            'requires_smp_subcontract' => false,
            'company_id' => Company::factory(),
        ];
    }

    /** Генподряд с нашими разделами и обязательным субподрядом СМП. */
    public function hot(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ContractKind::General,
            'subject' => 'Капитальный ремонт здания школы № '.fake()->numberBetween(1, 200),
            'sections' => [WorkSection::Eom, WorkSection::Ss, WorkSection::Aps],
            'requires_smp_subcontract' => true,
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ContractKind::General,
            'subject' => 'Реконструкция административного здания '.fake()->numberBetween(1, 99),
            'sections' => [WorkSection::Eom, WorkSection::Sks],
        ]);
    }
}
