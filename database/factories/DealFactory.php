<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Models\Contract;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * По умолчанию — новая заявка с сайта.
 *
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => DealSource::Site,
            'status' => DealStatus::New,
            'reached_status' => DealStatus::New,
            'external_id' => 'es12-'.fake()->unique()->numberBetween(1, 999_999),
            'received_at' => now(),
            'name' => fake('ru_RU')->name(),
            'phone' => '+79'.fake()->numerify('#########'),
            'email' => fake()->safeEmail(),
            'facility' => 'Квартира 60 м²',
            'service' => 'Электромонтаж под ключ',
            'origin' => 'Поиск в Яндексе',
        ];
    }

    /** Тендерная сделка: КП победителю контракта. */
    public function tender(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => DealSource::Tender,
            'status' => DealStatus::OfferSent,
            'reached_status' => DealStatus::OfferSent,
            'external_id' => null,
            'name' => null,
            'phone' => null,
            'email' => null,
            'facility' => null,
            'service' => null,
            'origin' => null,
            'contract_id' => Contract::factory(),
            'company_id' => fn (array $attributes): int => (int) Contract::query()->whereKey($attributes['contract_id'])->value('company_id'),
            'remind_on' => now()->addDays(3)->toDateString(),
        ]);
    }

    /** Статус и самый дальний этап; для выходов этап можно задать отдельно. */
    public function status(DealStatus $status, ?DealStatus $reached = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'reached_status' => $reached ?? ($status->stage() === null ? DealStatus::New : $status),
        ]);
    }

    public function amounts(?int $offerKopecks, ?int $contractKopecks = null, ?int $paidKopecks = null): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_amount_kopecks' => $offerKopecks,
            'contract_amount_kopecks' => $contractKopecks,
            'paid_kopecks' => $paidKopecks,
        ]);
    }
}
