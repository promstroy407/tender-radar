<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Enums\PriceSection;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Данные страницы «Наш просчёт» — общие для сметы тендера и расчёта из «Калькулятора».
 */
class EstimatePageBuilder
{
    private const LINES_PER_PAGE = 100;

    public function __construct(
        private readonly EstimateCalculator $calculator,
        private readonly PriceResolver $prices,
    ) {}

    /**
     * @return array<string, mixed> summary, lines, attention, pending, priceGroups, unitPrices, sections — для вида estimates.show
     */
    public function build(?Estimate $estimate, string $regionCode, bool $attention): array
    {
        $summary = null;
        $lines = null;
        if ($estimate !== null) {
            $estimate->loadMissing('files');
            $summary = $this->calculator->summarize($estimate, $estimate->lines()->with('priceItem')->get());

            $lines = $estimate->lines()->with(['priceItem', 'file'])
                ->when($attention, fn (Builder $q) => $q->where('match', '!=', LineMatch::Skip->value)
                    ->where(fn (Builder $q) => $q->whereNull('price_item_id')
                        ->orWhereHas('priceItem', fn (Builder $p) => $p->whereNull('work_price_kopecks'))))
                ->orderBy('estimate_file_id')
                ->orderBy('position')
                ->paginate(self::LINES_PER_PAGE)
                ->withQueryString();
        }

        // Цена за единицу каждой позиции прайса в регионе: по ГЭСН или вручную.
        $items = PriceItem::query()->where('is_active', true)->orderBy('name')->get();
        $unitPrices = [];
        foreach ($items as $item) {
            $unitPrices[$item->id] = $this->prices->unitPrice($item, $regionCode);
        }

        return [
            'summary' => $summary,
            'lines' => $lines,
            'attention' => $attention,
            'pending' => $estimate?->files->contains(fn (EstimateFile $f): bool => $f->status === EstimateFileStatus::Pending) ?? false,
            'priceGroups' => $items->groupBy(fn (PriceItem $item): string => $item->section->value),
            'unitPrices' => $unitPrices,
            'sections' => PriceSection::cases(),
        ];
    }
}
