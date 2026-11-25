<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LineMatch;
use App\Enums\PriceSection;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\Estimates\PriceResolver;
use Database\Seeders\MarketPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_price_is_average_of_sources_and_used_only_without_own_price(): void
    {
        $item = PriceItem::factory()->create([
            'unit' => 'м',
            'work_price_kopecks' => null,
            'gesn_code' => null,
            'market_sources' => [
                ['title' => 'Прайс 1', 'url' => 'https://a.example.test', 'price_kopecks' => 100_000],
                ['title' => 'Прайс 2', 'url' => 'https://b.example.test', 'price_kopecks' => 150_000],
                ['title' => 'Прайс 3', 'url' => 'https://c.example.test', 'price_kopecks' => 120_001],
            ],
        ]);
        $resolver = $this->app->make(PriceResolver::class);

        // (1 000 + 1 500 + 1 200,01) / 3 = 1 233,33666… → 1 233,34 ₽.
        $this->assertSame(123_334, $item->marketPriceKopecks());
        $this->assertSame(123_334, $resolver->unitPrice($item, '12'));
        $this->assertTrue($resolver->isMarket($item, '12'));

        $item->update(['work_price_kopecks' => 90_000]);
        $this->assertSame(90_000, $resolver->unitPrice($item->fresh() ?? $item, '12'));
        $this->assertFalse($resolver->isMarket($item->fresh() ?? $item, '12'));
    }

    public function test_seeder_loads_prices_from_file_without_touching_own_price(): void
    {
        $own = PriceItem::factory()->create(['name' => 'Монтаж троллейного шинопровода', 'unit' => 'м', 'work_price_kopecks' => 90_000]);
        $file = database_path(MarketPriceSeeder::FILE);
        $backup = is_file($file) ? (string) file_get_contents($file) : null;
        file_put_contents($file, (string) json_encode(['items' => [
            [
                'name' => 'Монтаж троллейного шинопровода', 'unit' => 'м', 'section' => 'electric', 'keywords' => 'шинопровод',
                'checked_at' => '2026-11-25',
                'sources' => [['title' => 'Прайс', 'url' => 'https://a.example.test', 'price_kopecks' => 120_000, 'note' => 'с НДС']],
            ],
            [
                'name' => 'Монтаж токосъёмника', 'unit' => 'шт', 'section' => 'electric', 'keywords' => 'токосъем',
                'checked_at' => '2026-11-25',
                'sources' => [['title' => 'Прайс', 'url' => 'https://b.example.test', 'price_kopecks' => 300_000]],
            ],
            // Без источника с https и ценой — пропускается.
            ['name' => 'Пустая', 'unit' => 'шт', 'section' => 'electric', 'keywords' => 'пуст', 'checked_at' => '2026-11-25',
                'sources' => [['title' => 'x', 'url' => 'http://x.test', 'price_kopecks' => 1]]],
        ]], JSON_UNESCAPED_UNICODE));

        try {
            $this->seed(MarketPriceSeeder::class);
            $this->seed(MarketPriceSeeder::class);
        } finally {
            $backup === null ? unlink($file) : file_put_contents($file, $backup);
        }

        $own->refresh();
        $this->assertSame(90_000, $own->work_price_kopecks);
        $this->assertSame(120_000, $own->marketPriceKopecks());

        $created = PriceItem::query()->where('name', 'Монтаж токосъёмника')->sole();
        $this->assertSame(PriceSection::Electric, $created->section);
        $this->assertSame(300_000, $created->marketPriceKopecks());
        $this->assertSame('2026-11-25', $created->market_checked_at?->toDateString());
        $this->assertSame(0, PriceItem::query()->where('name', 'Пустая')->count());
    }

    public function test_price_gaps_list_names_only(): void
    {
        $empty = PriceItem::factory()->create(['name' => 'Монтаж токосъёмника', 'unit' => 'шт', 'work_price_kopecks' => null, 'gesn_code' => null, 'market_sources' => null]);
        PriceItem::factory()->create(['name' => 'С ценой', 'work_price_kopecks' => 1_000]);
        EstimateLine::factory()->count(2)->create(['price_item_id' => $empty->id, 'match' => LineMatch::Auto]);
        EstimateLine::factory()->create(['name' => 'Монтаж секции шинопровода', 'unit' => 'шт', 'price_item_id' => null, 'match' => LineMatch::Auto]);
        EstimateLine::factory()->create(['name' => 'ЭКСПЛИКАЦИЯ', 'unit' => 'шт', 'price_item_id' => null, 'match' => LineMatch::Auto]);

        $this->getJson('/api/price-gaps')
            ->assertOk()
            ->assertJsonPath('items_without_price.0.name', 'Монтаж токосъёмника')
            ->assertJsonPath('items_without_price.0.lines', 2)
            ->assertJsonCount(1, 'items_without_price')
            ->assertJsonPath('unmatched_lines.0.name', 'Монтаж секции шинопровода')
            ->assertJsonCount(1, 'unmatched_lines')
            ->assertJsonMissingPath('unmatched_lines.0.estimate_id');
    }

    public function test_prices_page_shows_market_sources(): void
    {
        PriceItem::factory()->create([
            'name' => 'Монтаж токосъёмника', 'unit' => 'шт', 'work_price_kopecks' => null, 'gesn_code' => null,
            'market_sources' => [['title' => 'Прайс подрядчика', 'url' => 'https://a.example.test/price', 'price_kopecks' => 300_000]],
            'market_checked_at' => '2026-11-25',
        ]);

        $this->actingAs(User::factory()->admin()->create())->get('/prices')
            ->assertOk()
            ->assertSee('рынок ≈ 3000 ₽/шт')
            ->assertSee('https://a.example.test/price')
            ->assertSee('проверено 25.11.2026');
    }
}
