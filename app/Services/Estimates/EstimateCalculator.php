<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\LineMatch;
use App\Models\Estimate;
use App\Models\EstimateLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Считает быструю смету: работа — по нашему прайсу, материалы — из сметы заказчика.
 */
class EstimateCalculator
{
    /**
     * @param  Collection<int, EstimateLine>  $lines  с загруженным priceItem
     */
    public function summarize(Estimate $estimate, Collection $lines): EstimateSummary
    {
        $work = $material = $priced = $unmatched = $noPrice = $skipped = 0;

        foreach ($lines as $line) {
            if ($line->match === LineMatch::Skip) {
                $skipped++;

                continue;
            }

            $material += $line->material_kopecks ?? 0;
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
            materialKopecks: $material,
            contractKopecks: $estimate->contract->amount_kopecks,
            linesTotal: $lines->count(),
            linesPriced: $priced,
            linesUnmatched: $unmatched,
            linesNoPrice: $noPrice,
            linesSkipped: $skipped,
        );
    }
}
