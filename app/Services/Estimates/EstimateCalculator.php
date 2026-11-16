<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\LineMatch;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Считает быструю смету — только работы:
 *  - из нормативных смет заказчика (ГЭСН): итог минус материалы и оборудование;
 *  - позиции ведомостей — по прайсу, если в нём заданы цены.
 * Материалы считаются отдельно (стоимость из сметы заказчика сохраняется в позициях).
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

        $normative = $normativeFiles = 0;
        foreach ($estimate->files as $file) {
            /** @var EstimateFile $file */
            $totals = $file->normativeTotals();
            if ($totals !== null) {
                $normative += $totals->worksKopecks();
                $normativeFiles++;
            }
        }

        return new EstimateSummary(
            normativeKopecks: $normative,
            normativeFiles: $normativeFiles,
            priceListKopecks: $work,
            contractKopecks: $estimate->contract->amount_kopecks,
            linesTotal: $lines->count(),
            linesPriced: $priced,
            linesUnmatched: $unmatched,
            linesNoPrice: $noPrice,
            linesSkipped: $skipped,
        );
    }
}
