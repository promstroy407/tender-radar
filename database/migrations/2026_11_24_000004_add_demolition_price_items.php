<?php

declare(strict_types=1);

use App\Enums\LineMatch;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Services\Estimates\PriceMatcher;
use Database\Seeders\PriceItemSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Демонтаж — отдельные позиции прайса (решение заказчика 24.11.2026): раньше строки
 * «…(Демонтаж извещателя)» считались по цене монтажа. Добавляем позиции без цены,
 * уточняем ключевые слова охранного датчика (если их не правили вручную) и заново
 * сопоставляем строки, сопоставленные автоматически: демонтаж и ошибочные «датчики».
 * Ручные решения не трогаем.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (PriceItemSeeder::demolition() as [$section, $name, $unit, $price, $keywords]) {
            DB::table('price_items')->insertOrIgnore([
                'section' => $section->value,
                'name' => $name,
                'unit' => $unit,
                'work_price_kopecks' => $price,
                'keywords' => $keywords,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sensor = 'Монтаж датчика движения или открытия';
        DB::table('price_items')->where('name', $sensor)->where('keywords', PriceItemSeeder::SECURITY_SENSOR_KEYWORDS_OLD)
            ->update(['keywords' => PriceItemSeeder::SECURITY_SENSOR_KEYWORDS, 'updated_at' => $now]);

        $matcher = app(PriceMatcher::class);
        $items = PriceItem::query()->where('is_active', true)->get();
        $sensorId = PriceItem::query()->where('name', $sensor)->value('id');

        EstimateLine::query()->where('match', LineMatch::Auto->value)
            ->where(fn (Builder $q): Builder => $q->where('name', 'ilike', '%демонтаж%')
                ->orWhere('name', 'ilike', '%разборк%')
                ->orWhere('name', 'ilike', '%снятие%')
                ->orWhere('price_item_id', $sensorId))
            ->chunkById(500, function (Collection $lines) use ($matcher, $items): void {
                foreach ($lines as $line) {
                    /** @var EstimateLine $line */
                    $line->update(['price_item_id' => $matcher->match($line->name, $line->unit, $items)?->id]);
                }
            });
    }

    public function down(): void
    {
        $names = array_map(fn (array $item): string => $item[1], PriceItemSeeder::demolition());
        $ids = DB::table('price_items')->whereIn('name', $names)->pluck('id');
        DB::table('estimate_lines')->whereIn('price_item_id', $ids)->update(['price_item_id' => null]);
        DB::table('price_items')->whereIn('id', $ids)->delete();
    }
};
