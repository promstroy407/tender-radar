<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OfferChannel;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Отправки КП победителям: кому, когда, через что. Дальнейшая судьба — у сделки.
 */
class OfferService
{
    public function __construct(
        private readonly DealService $deals,
        private readonly ConnectionInterface $db,
    ) {}

    public function recordSent(Contract $contract, OfferChannel $channel, User $user): Offer
    {
        return $this->db->transaction(function () use ($contract, $channel, $user): Offer {
            $deal = $this->deals->markOfferSent($contract, $user);

            return Offer::query()->updateOrCreate(
                ['contract_id' => $contract->id],
                [
                    'deal_id' => $deal->id,
                    'channel' => $channel,
                    'sent_at' => Carbon::now(),
                    'user_id' => $user->id,
                ],
            );
        });
    }

    /** «Не интересно»: контракт уходит из ленты, КП не отправляется. */
    public function dismiss(Contract $contract, User $user): Deal
    {
        return $this->deals->dismiss($contract, $user);
    }

    /** Сколько КП ушло по почте с начала текущих суток — для дневного лимита. */
    public function emailsSentToday(): int
    {
        return Offer::query()
            ->where('channel', OfferChannel::Email)
            ->where('sent_at', '>=', Carbon::today())
            ->count();
    }
}
