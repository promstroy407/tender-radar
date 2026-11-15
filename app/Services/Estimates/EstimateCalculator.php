<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\LineMatch;
use App\Models\Estimate;
use App\Models\EstimateLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Считает быструю смету: только работы по нашему прайсу. Материалы считаются отдельно
 * (стоимость материала из сметы заказчика сохраняется в позиции для этого).
 */
class EstimateCalculator
{
    /**
     * @param  Collection<int, EstimateLine>  $lines  с загруженным priceItem
     */
    public function summarize(Estimate $estimate, Collection $lines): EstimateSummary
    {
        $work = $priced = $unmatched = $noPrice = $skipped = 0;

        foreach ($lines as $line) {
            if ($line->match === LineMatch::Skip) {
                $skipped++;

                continue;
            }

            $lineWork = $line->workKopecks();

            if ($line->priceItem === null) {
                $unmatched++;
            } elseif ($lineWork === null) {
                $noPrice++;
            } else {
                $priced++;
                $work += $lineWork;
            }
        }

        return new EstimateSummary(
            workKopecks: $work,
            contractKopecks: $estimate->contract->amount_kopecks,
            linesTotal: $lines->count(),
            linesPriced: $priced,
            linesUnmatched: $unmatched,
            linesNoPrice: $noPrice,
            linesSkipped: $skipped,
        );
    }
}
