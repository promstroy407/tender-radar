<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\LineMatch;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Чего не хватает в прайсе — для ежедневного поиска рыночных цен:
 * позиции без цены (ни вашей, ни нормы, ни рыночной) и работы из смет, не
 * сопоставленные ни с одной позицией. Только названия и единицы: без заказчиков,
 * сумм и ссылок на тендеры.
 */
class PriceGapReport
{
    private const UNMATCHED_LIMIT = 100;

    public function __construct(
        private readonly LineFilter $filter,
    ) {}

    /**
     * @return array{items_without_price: list<array{name: string, unit: string, section: string, keywords: string, lines: int}>, unmatched_lines: list<array{name: string, unit: string|null, count: int}>}
     */
    public function build(): array
    {
        $items = PriceItem::query()
            ->where('is_active', true)
            ->whereNull('work_price_kopecks')
            ->whereNull('gesn_code')
            ->whereNull('market_sources')
            ->withCount(['lines' => fn (Builder $q): Builder => $q->where('match', '!=', LineMatch::Skip->value)])
            ->orderByDesc('lines_count')
            ->orderBy('name')
            ->get();

        $withoutPrice = [];
        foreach ($items as $item) {
            $withoutPrice[] = [
                'name' => $item->name,
                'unit' => $item->unit,
                'section' => $item->section->value,
                'keywords' => $item->keywords,
                'lines' => (int) $item->getAttribute('lines_count'),
            ];
        }

        $rows = EstimateLine::query()
            ->selectRaw('name, unit, count(*) as lines_count')
            ->whereNull('price_item_id')
            ->where('match', LineMatch::Auto->value)
            ->groupBy('name', 'unit')
            ->orderByDesc('lines_count')
            ->limit(self::UNMATCHED_LIMIT * 2)
            ->toBase()
            ->get();

        $unmatched = [];
        foreach ($rows as $row) {
            $name = (string) $row->name;
            $unit = $row->unit === null ? null : (string) $row->unit;
            if ($this->filter->isJunk($name, $unit) || count($unmatched) >= self::UNMATCHED_LIMIT) {
                continue;
            }
            $unmatched[] = ['name' => mb_substr($name, 0, 300), 'unit' => $unit, 'count' => (int) $row->lines_count];
        }

        return ['items_without_price' => $withoutPrice, 'unmatched_lines' => $unmatched];
    }
}
