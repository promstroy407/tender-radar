<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OfferChannel;
use App\Enums\OfferStatus;
use App\Models\Contract;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'status' => OfferStatus::Sent,
            'channel' => OfferChannel::Email,
            'sent_at' => now(),
            'remind_on' => now()->addDays(3)->toDateString(),
            'note' => null,
        ];
    }

    public function status(OfferStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Dismissed,
            'channel' => null,
            'sent_at' => null,
            'remind_on' => null,
        ]);
    }
}
