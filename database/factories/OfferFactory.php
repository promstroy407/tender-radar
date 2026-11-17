<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OfferChannel;
use App\Models\Deal;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Отправка привязывается к тендерной сделке и её контракту.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deal_id' => Deal::factory()->tender(),
            'contract_id' => fn (array $attributes): int => (int) Deal::query()->whereKey($attributes['deal_id'])->value('contract_id'),
            'channel' => OfferChannel::Email,
            'sent_at' => now(),
        ];
    }
}
