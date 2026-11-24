<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\LineMatch;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Считает быструю смету — только работы:
 *  - из нормативных смет заказчика (ГЭСН): итог минус материалы и оборудование;
 *  - позиции ведомостей — по нормативной цене прайса в регионе тендера (ГЭСН),
 *    а без нормы — по цене, вписанной вручную.
 * Материалы считаются отдельно (стоимость из сметы заказчика сохраняется в позициях).
 */
class EstimateCalculator
{
    /** Сколько самых дорогих работ показывать в просчёте. */
    private const TOP_LINES = 15;

    public function __construct(
        private readonly PriceResolver $prices,
    ) {}

    /**
     * @param  Collection<int, EstimateLine>  $lines  с загруженным priceItem
     */
    public function summarize(Estimate $estimate, Collection $lines): EstimateSummary
    {
        $work = $priced = $unmatched = $noPrice = $skipped = 0;
        $bySection = [];
        $top = [];
        $unpriced = [];

        foreach ($lines as $line) {
            if ($line->match === LineMatch::Skip) {
                $skipped++;

                continue;
            }

            $lineWork = $line->priceItem === null ? null
                : $line->workKopecks($this->prices->unitPrice($line->priceItem, $estimate->contract->region_code));

            if ($line->priceItem === null) {
                $unmatched++;
                $this->addUnpriced($unpriced, $line, null);
            } elseif ($lineWork === null) {
                $noPrice++;
                $this->addUnpriced($unpriced, $line, $line->priceItem);
            } else {
                $priced++;
                $work += $lineWork;

                $section = $line->priceItem->section;
                $bySection[$section->value] ??= ['section' => $section, 'kopecks' => 0, 'lines' => 0];
                $bySection[$section->value]['kopecks'] += $lineWork;
                $bySection[$section->value]['lines']++;
                $top[] = ['name' => $line->name, 'quantity_milli' => $line->quantity_milli, 'unit' => $line->unit, 'kopecks' => $lineWork];
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
            sections: $this->sortByKopecks(array_values($bySection)),
            topLines: array_slice($this->sortByKopecks($top), 0, self::TOP_LINES),
            unpriced: array_values($unpriced),
        );
    }

    /**
     * Одинаковые строки (имя + единица) складываются: в проекте одна позиция
     * часто стоит на нескольких листах.
     *
     * @param  array<string, array{name: string, quantity_milli: int, unit: string|null, price_item: PriceItem|null}>  $unpriced
     */
    private function addUnpriced(array &$unpriced, EstimateLine $line, ?PriceItem $item): void
    {
        $key = mb_strtolower(trim($line->name)).'|'.mb_strtolower((string) $line->unit);
        $unpriced[$key] ??= ['name' => $line->name, 'quantity_milli' => 0, 'unit' => $line->unit, 'price_item' => $item];
        $unpriced[$key]['quantity_milli'] += $line->quantity_milli;
    }

    /**
     * @template T of array{kopecks: int}
     *
     * @param  list<T>  $rows
     * @return list<T>
     */
    private function sortByKopecks(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => $b['kopecks'] <=> $a['kopecks']);

        return $rows;
    }
}
