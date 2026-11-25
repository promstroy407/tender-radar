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
 * Слишком широкие ключевые слова у двух новых позиций: «секция», «заглушка»
 * и «испытание» находили радиаторы, сантехнику и испытания трубопроводов.
 * Сужаем (если не правили вручную) и заново сопоставляем строки, которые
 * программа сама к ним привязала. Ручные решения не трогаем.
 */
return new class extends Migration
{
    /** @var array<string, array{string, string}> */
    private const CHANGES = [
        'Монтаж секции, заглушки или компенсатора шинопровода' => [PriceItemSeeder::BUSWAY_PARTS_KEYWORDS_OLD, PriceItemSeeder::BUSWAY_PARTS_KEYWORDS],
        'Испытания и измерения электроустановки' => [PriceItemSeeder::TESTS_KEYWORDS_OLD, PriceItemSeeder::TESTS_KEYWORDS],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $name => [$old, $new]) {
            DB::table('price_items')->where('name', $name)->where('keywords', $old)->update(['keywords' => $new, 'updated_at' => now()]);
        }

        $ids = PriceItem::query()->whereIn('name', array_keys(self::CHANGES))->pluck('id');
        $matcher = app(PriceMatcher::class);
        $items = PriceItem::query()->where('is_active', true)->get();

        EstimateLine::query()->where('match', LineMatch::Auto->value)->whereIn('price_item_id', $ids)
            ->chunkById(500, function (Collection $lines) use ($matcher, $items): void {
                foreach ($lines as $line) {
                    /** @var EstimateLine $line */
                    $line->update(['price_item_id' => $matcher->match($line->name, $line->unit, $items)?->id]);
                }
            });
    }

    public function down(): void
    {
        foreach (self::CHANGES as $name => [$old, $new]) {
            DB::table('price_items')->where('name', $name)->where('keywords', $new)->update(['keywords' => $old]);
        }
    }
};
