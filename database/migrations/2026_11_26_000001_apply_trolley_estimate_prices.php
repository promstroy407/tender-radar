<?php

declare(strict_types=1);

use App\Enums\LineMatch;
use App\Enums\PriceSection;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Services\Estimates\PriceMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Решение заказчика 26.11.2026: цены из его сметы на замену троллеев (максимальные по
 * рынку, итог 430 875 ₽) — в наш прайс; рыночная цена записывается как наша и берётся
 * максимальная. Поэтому по каждой позиции — большее из сметы и максимума рынка.
 * Токосъёмники в смете «за кран» (2 на кран): 5 000 → 2 500 ₽/шт, 1 500 → 750 ₽/шт.
 * Позициям, у которых уже есть рыночные цены, а своей нет, — максимум рынка.
 * Две новые позиции из сметы; строки, сопоставленные автоматически, — заново.
 */
return new class extends Migration
{
    /** Цена из сметы заказчика, копейки за единицу позиции. */
    private const ESTIMATE = [
        'Демонтаж троллеев или шинопровода' => 50_000,
        'Демонтаж кабеля или провода' => 4_500,
        'Демонтаж рубильника' => 15_000,
        'Демонтаж токосъёмника' => 75_000,
        'Монтаж троллейного шинопровода' => 255_000,
        'Монтаж блока подвода питания шинопровода' => 275_000,
        'Монтаж секции, заглушки или компенсатора шинопровода' => 260_000,
        'Установка световой индикации напряжения' => 260_000,
        'Монтаж токосъёмника' => 250_000,
        'Установка рубильника или ящика с рубильником' => 300_000,
    ];

    /** @var list<array{string, string, int, string}> */
    private const NEW_ITEMS = [
        ['Прокладка питающего кабеля по лоткам и стенам', 'м', 29_000, 'кабел питан, питающ кабел'],
        ['Разделка и подключение жил кабеля до 16 мм²', 'шт', 16_000, 'подключен жил, разделк жил, присоединен жил, оконцеван жил'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::NEW_ITEMS as [$name, $unit, $price, $keywords]) {
            DB::table('price_items')->insertOrIgnore([
                'section' => PriceSection::Electric->value,
                'name' => $name,
                'unit' => $unit,
                'work_price_kopecks' => $price,
                'keywords' => $keywords,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (PriceItem::query()->where(fn (Builder $q): Builder => $q->whereIn('name', array_keys(self::ESTIMATE))->orWhereNotNull('market_sources'))->get() as $item) {
            /** @var PriceItem $item */
            $candidates = array_filter([self::ESTIMATE[$item->name] ?? null, $item->marketPriceKopecks()], fn (?int $price): bool => $price !== null);
            if ($candidates === []) {
                continue;
            }
            // Своя цена, если она уже выше, не снижается.
            $price = max([...$candidates, $item->work_price_kopecks ?? 0]);
            if ($price !== $item->work_price_kopecks) {
                $item->update(['work_price_kopecks' => $price]);
            }
        }

        $matcher = app(PriceMatcher::class);
        $items = PriceItem::query()->where('is_active', true)->get();
        EstimateLine::query()->where('match', LineMatch::Auto->value)
            ->chunkById(500, function (Collection $lines) use ($matcher, $items): void {
                foreach ($lines as $line) {
                    /** @var EstimateLine $line */
                    $id = $matcher->match($line->name, $line->unit, $items)?->id;
                    if ($id !== $line->price_item_id) {
                        $line->update(['price_item_id' => $id]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Цены — решение заказчика, назад не откатываются; новые позиции убираются.
        $ids = DB::table('price_items')->whereIn('name', array_map(fn (array $item): string => $item[0], self::NEW_ITEMS))->pluck('id');
        DB::table('estimate_lines')->whereIn('price_item_id', $ids)->update(['price_item_id' => null]);
        DB::table('price_items')->whereIn('id', $ids)->delete();
    }
};
