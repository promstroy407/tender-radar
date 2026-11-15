<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\OfferChange;
use App\Enums\OfferChannel;
use App\Enums\OfferStatus;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Учёт отправленных КП: кому, когда, через что и что дальше.
 */
class OfferService
{
    /** Через сколько дней напомнить о себе, если победитель молчит. */
    public const REMIND_AFTER_DAYS = 3;

    public function recordSent(Contract $contract, OfferChannel $channel, User $user): Offer
    {
        $now = Carbon::now();

        return Offer::query()->updateOrCreate(
            ['contract_id' => $contract->id],
            [
                'status' => OfferStatus::Sent,
                'channel' => $channel,
                'sent_at' => $now,
                'remind_on' => $now->copy()->addDays(self::REMIND_AFTER_DAYS)->toDateString(),
                'user_id' => $user->id,
            ],
        );
    }

    /** «Не интересно»: контракт уходит из ленты, КП не отправляется. */
    public function dismiss(Contract $contract, User $user): Offer
    {
        return Offer::query()->updateOrCreate(
            ['contract_id' => $contract->id],
            ['status' => OfferStatus::Dismissed, 'remind_on' => null, 'user_id' => $user->id],
        );
    }

    public function change(Offer $offer, OfferChange $change): Offer
    {
        $offer->fill([
            'status' => $change->status,
            'remind_on' => $change->remindOn?->toDateString(),
            'note' => $change->note,
        ])->save();

        return $offer;
    }

    /**
     * КП одной вкладки «В работе». Отправленные — по дате напоминания, остальные — свежие сверху.
     *
     * @return Collection<int, Offer>
     */
    public function board(OfferStatus $status): Collection
    {
        $query = Offer::query()
            ->with(['contract.company', 'contract.region'])
            ->where('status', $status);

        if ($status === OfferStatus::Sent) {
            $query->orderBy('remind_on')->orderBy('id');
        } else {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        }

        return $query->get();
    }

    /**
     * Сколько КП в каждой вкладке.
     *
     * @return array<string, int> значение статуса => количество
     */
    public function counts(): array
    {
        $counts = Offer::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];
        foreach (OfferStatus::board() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
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
