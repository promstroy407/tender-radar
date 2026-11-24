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
 * «Световые настенные указатели» из норм не узнавались как табло «Выход».
 * Добавляем слова «указатель», «светоуказатель» (если ключевые слова не правили
 * вручную) и сопоставляем заново строки, которые остались без позиции.
 */
return new class extends Migration
{
    private const NAME = 'Монтаж оповещателя или табло «Выход»';

    public function up(): void
    {
        DB::table('price_items')->where('name', self::NAME)->where('keywords', PriceItemSeeder::SIGN_KEYWORDS_OLD)
            ->update(['keywords' => PriceItemSeeder::SIGN_KEYWORDS, 'updated_at' => now()]);

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
        DB::table('price_items')->where('name', self::NAME)->where('keywords', PriceItemSeeder::SIGN_KEYWORDS)
            ->update(['keywords' => PriceItemSeeder::SIGN_KEYWORDS_OLD]);
    }
};
