<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\FunnelReport;
use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Models\Deal;

/**
 * Воронка сделок: сколько дошло до каждого этапа, конверсия, деньги, источники.
 *
 * До этапа «дошла» сделка, чей самый дальний этап не раньше него: отказ после
 * переговоров считается на этапах до переговоров включительно.
 */
class FunnelService
{
    public function report(?DealSource $source): FunnelReport
    {
        $query = Deal::query()
            ->selectRaw('source, status, reached_status, count(*) as deals')
            ->selectRaw('coalesce(sum(offer_amount_kopecks), 0) as offer_sum')
            ->selectRaw('coalesce(sum(contract_amount_kopecks), 0) as contract_sum')
            ->selectRaw('coalesce(sum(paid_kopecks), 0) as paid_sum')
            ->selectRaw('coalesce(sum(contract_amount_kopecks - coalesce(paid_kopecks, 0)), 0) as remainder_sum')
            ->groupBy('source', 'status', 'reached_status');

        if ($source !== null) {
            $query->where('source', $source);
        }

        $chain = DealStatus::chain();
        $contractStage = DealStatus::Contract->stage() ?? 0;

        $reached = array_fill(0, count($chain), 0);
        $now = [];
        $bySource = [];
        $total = $offer = $contract = $paid = $remainder = 0;

        foreach ($query->toBase()->get() as $row) {
            $count = (int) $row->deals;
            $status = DealStatus::from((string) $row->status);
            $stage = DealStatus::from((string) $row->reached_status)->stage() ?? 0;
            $dealSource = (string) $row->source;
            $contractSum = (int) $row->contract_sum;

            $total += $count;
            $offer += (int) $row->offer_sum;
            $contract += $contractSum;
            $paid += (int) $row->paid_sum;
            $remainder += (int) $row->remainder_sum;
            $now[$status->value] = ($now[$status->value] ?? 0) + $count;

            for ($i = 0; $i <= $stage; $i++) {
                $reached[$i] += $count;
            }

            $bySource[$dealSource] ??= ['deals' => 0, 'contracts' => 0, 'contractKopecks' => 0];
            $bySource[$dealSource]['deals'] += $count;
            $bySource[$dealSource]['contractKopecks'] += $contractSum;
            if ($stage >= $contractStage) {
                $bySource[$dealSource]['contracts'] += $count;
            }
        }

        $stages = [];
        foreach ($chain as $i => $status) {
            $stages[] = [
                'status' => $status,
                'reached' => $reached[$i],
                'now' => $now[$status->value] ?? 0,
                'fromPrevious' => $i === 0 ? null : $this->tenths($reached[$i], $reached[$i - 1]),
                'fromAll' => $this->tenths($reached[$i], $total),
            ];
        }

        $exits = [];
        foreach ([DealStatus::Refused, DealStatus::Dismissed] as $status) {
            $exits[] = ['status' => $status, 'count' => $now[$status->value] ?? 0];
        }

        $sources = [];
        foreach (DealSource::cases() as $item) {
            if ($source !== null && $item !== $source) {
                continue;
            }
            $sources[] = ['source' => $item] + ($bySource[$item->value] ?? ['deals' => 0, 'contracts' => 0, 'contractKopecks' => 0]);
        }

        return new FunnelReport($total, $stages, $exits, $offer, $contract, $paid, $remainder, $sources);
    }

    /** Доля в десятых процента с округлением половины вверх; null — делить не на что. */
    private function tenths(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : intdiv($part * 1000 + intdiv($whole, 2), $whole);
    }
}
