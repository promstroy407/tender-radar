<?php

declare(strict_types=1);

use App\Enums\LineMatch;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Services\Estimates\PriceMatcher;
use Database\Seeders\PriceItemSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Позиции промышленного электромонтажа (троллейный шинопровод, токосъёмники, рубильники,
 * испытания, документация) — без цены. Строки, оставшиеся без позиции, сопоставляем заново.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (PriceItemSeeder::industrial() as [$section, $name, $unit, $price, $keywords]) {
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

        $matcher = app(PriceMatcher::class);
        $items = PriceItem::query()->where('is_active', true)->get();

        EstimateLine::query()->where('match', LineMatch::Auto->value)->whereNull('price_item_id')
            ->chunkById(500, function (Collection $lines) use ($matcher, $items): void {
                foreach ($lines as $line) {
                    /** @var EstimateLine $line */
                    $item = $matcher->match($line->name, $line->unit, $items);
                    if ($item !== null) {
                        $line->update(['price_item_id' => $item->id]);
                    }
                }
            });
    }

    public function down(): void
    {
        $names = array_map(fn (array $item): string => $item[1], PriceItemSeeder::industrial());
        $ids = DB::table('price_items')->whereIn('name', $names)->pluck('id');
        DB::table('estimate_lines')->whereIn('price_item_id', $ids)->update(['price_item_id' => null]);
        DB::table('price_items')->whereIn('id', $ids)->delete();
    }
};
