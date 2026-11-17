<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DealChange;
use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Сделки общей воронки: тендеры и заявки клиентов.
 */
class DealService
{
    /** Через сколько дней напомнить о себе, если клиент молчит. */
    public const REMIND_AFTER_DAYS = 3;

    /** КП победителю отправлено: сделка по контракту создаётся или переходит на «КП отправлено». */
    public function markOfferSent(Contract $contract, User $user): Deal
    {
        $deal = $this->forContract($contract, $user);
        $stage = $deal->status->stage();

        // Повторная отправка не откатывает сделку, ушедшую дальше (переговоры, договор).
        if ($stage === null || $stage < (DealStatus::OfferSent->stage() ?? 0)) {
            $this->moveTo($deal, DealStatus::OfferSent);
        }

        $deal->remind_on = Carbon::today()->addDays(self::REMIND_AFTER_DAYS);
        $deal->user_id = $user->id;
        $deal->save();

        return $deal;
    }

    /** «Не интересно»: тендер уходит из ленты, КП не отправляется. */
    public function dismiss(Contract $contract, User $user): Deal
    {
        $deal = $this->forContract($contract, $user);
        $this->moveTo($deal, DealStatus::Dismissed);
        $deal->remind_on = null;
        $deal->save();

        return $deal;
    }

    public function changeStatus(Deal $deal, DealStatus $status): Deal
    {
        $this->moveTo($deal, $status);

        if ($status->isClosed()) {
            $deal->remind_on = null;
        }

        $deal->save();

        return $deal;
    }

    public function update(Deal $deal, DealChange $change): Deal
    {
        $deal->fill([
            'offer_amount_kopecks' => $change->offerAmountKopecks,
            'contract_amount_kopecks' => $change->contractAmountKopecks,
            'paid_kopecks' => $change->paidKopecks,
            'remind_on' => $change->remindOn?->toDateString(),
            'note' => $change->note,
        ])->save();

        return $deal;
    }

    /**
     * Сделки одного статуса, с фильтром по источнику. Открытые — по дате напоминания.
     *
     * @return Collection<int, Deal>
     */
    public function list(DealStatus $status, ?DealSource $source): Collection
    {
        $query = Deal::query()
            ->with(['contract.region', 'company', 'offer'])
            ->where('status', $status);

        if ($source !== null) {
            $query->where('source', $source);
        }

        if ($status->isClosed()) {
            $query->orderByDesc('updated_at');
        } else {
            $query->orderByRaw('remind_on is null')->orderBy('remind_on')->orderByDesc('received_at');
        }

        return $query->orderByDesc('id')->get();
    }

    /**
     * Сколько сделок в каждом статусе.
     *
     * @return array<string, int> значение статуса => количество
     */
    public function counts(?DealSource $source): array
    {
        $query = Deal::query()->selectRaw('status, count(*) as aggregate')->groupBy('status');

        if ($source !== null) {
            $query->where('source', $source);
        }

        $counts = $query->pluck('aggregate', 'status');

        $result = [];
        foreach (DealStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    private function forContract(Contract $contract, User $user): Deal
    {
        return Deal::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'source' => DealSource::Tender,
                'status' => DealStatus::New,
                'reached_status' => DealStatus::New,
                'company_id' => $contract->company_id,
                'received_at' => Carbon::now(),
                'user_id' => $user->id,
            ],
        );
    }

    /** Меняет статус и запоминает самый дальний этап воронки, до которого дошла сделка. */
    private function moveTo(Deal $deal, DealStatus $status): void
    {
        $deal->status = $status;

        $stage = $status->stage();
        if ($stage !== null && $stage > ($deal->reached_status->stage() ?? 0)) {
            $deal->reached_status = $status;
        }
    }
}
