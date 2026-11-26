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

    public function test_market_price_is_maximum_of_sources_and_used_only_without_own_price(): void
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

        // Берётся максимальная из источников: 1 500 ₽.
        $this->assertSame(150_000, $item->marketPriceKopecks());
        $this->assertSame(150_000, $resolver->unitPrice($item, '12'));
        $this->assertTrue($resolver->isMarket($item, '12'));

        $item->update(['work_price_kopecks' => 90_000]);
        $this->assertSame(90_000, $resolver->unitPrice($item->fresh() ?? $item, '12'));
        $this->assertFalse($resolver->isMarket($item->fresh() ?? $item, '12'));
    }

    public function test_seeder_raises_own_price_to_market_maximum_once_per_check(): void
    {
        $own = PriceItem::factory()->create(['name' => 'Тестовый монтаж шинопровода', 'unit' => 'м', 'work_price_kopecks' => 90_000]);
        $file = database_path(MarketPriceSeeder::FILE);
        $backup = is_file($file) ? (string) file_get_contents($file) : null;
        file_put_contents($file, (string) json_encode(['items' => [
            [
                'name' => 'Тестовый монтаж шинопровода', 'unit' => 'м', 'section' => 'electric', 'keywords' => 'шинопровод',
                'checked_at' => '2026-11-25',
                'sources' => [['title' => 'Прайс', 'url' => 'https://a.example.test', 'price_kopecks' => 120_000, 'note' => 'с НДС']],
            ],
            [
                'name' => 'Тестовый токосъёмник', 'unit' => 'шт', 'section' => 'electric', 'keywords' => 'токосъем',
                'checked_at' => '2026-11-25',
                'sources' => [['title' => 'Прайс', 'url' => 'https://b.example.test', 'price_kopecks' => 300_000]],
            ],
            // Без источника с https и ценой — пропускается.
            ['name' => 'Пустая', 'unit' => 'шт', 'section' => 'electric', 'keywords' => 'пуст', 'checked_at' => '2026-11-25',
                'sources' => [['title' => 'x', 'url' => 'http://x.test', 'price_kopecks' => 1]]],
        ]], JSON_UNESCAPED_UNICODE));

        try {
            $this->seed(MarketPriceSeeder::class);
            // Цену снизили вручную — повторная выкладка без новой проверки её не трогает.
            PriceItem::query()->where('name', 'Тестовый монтаж шинопровода')->update(['work_price_kopecks' => 100_000]);
            $this->seed(MarketPriceSeeder::class);
            $this->assertSame(100_000, PriceItem::query()->where('name', 'Тестовый монтаж шинопровода')->value('work_price_kopecks'));
            PriceItem::query()->where('name', 'Тестовый монтаж шинопровода')->update(['work_price_kopecks' => 120_000]);
        } finally {
            $backup === null ? unlink($file) : file_put_contents($file, $backup);
        }

        // Максимум рынка выше вашей цены — записан в вашу.
        $own->refresh();
        $this->assertSame(120_000, $own->work_price_kopecks);
        $this->assertSame(120_000, $own->marketPriceKopecks());

        $created = PriceItem::query()->where('name', 'Тестовый токосъёмник')->sole();
        $this->assertSame(PriceSection::Electric, $created->section);
        $this->assertSame(300_000, $created->work_price_kopecks);
        $this->assertSame(300_000, $created->marketPriceKopecks());
        $this->assertSame('2026-11-25', $created->market_checked_at?->toDateString());
        $this->assertSame(0, PriceItem::query()->where('name', 'Пустая')->count());
    }

    public function test_price_gaps_list_names_only(): void
    {
        $empty = PriceItem::factory()->create(['name' => 'Тестовый токосъёмник', 'unit' => 'шт', 'work_price_kopecks' => null, 'gesn_code' => null, 'market_sources' => null]);
        PriceItem::factory()->create(['name' => 'С ценой', 'work_price_kopecks' => 1_000]);
        EstimateLine::factory()->count(2)->create(['price_item_id' => $empty->id, 'match' => LineMatch::Auto]);
        EstimateLine::factory()->create(['name' => 'Монтаж секции шинопровода', 'unit' => 'шт', 'price_item_id' => null, 'match' => LineMatch::Auto]);
        EstimateLine::factory()->create(['name' => 'ЭКСПЛИКАЦИЯ', 'unit' => 'шт', 'price_item_id' => null, 'match' => LineMatch::Auto]);

        $response = $this->getJson('/api/price-gaps')->assertOk();

        // Сверху — позиция без цены, по которой больше всего строк смет.
        $this->assertSame(['name' => 'Тестовый токосъёмник', 'unit' => 'шт', 'section' => 'fire', 'keywords' => 'извещател дым', 'lines' => 2], $response->json('items_without_price.0'));
        $names = array_column((array) $response->json('items_without_price'), 'name');
        $this->assertNotContains('С ценой', $names);
        // Очередь пересмотра — все позиции, без цен.
        $this->assertContains('С ценой', array_column((array) $response->json('items_to_review'), 'name'));
        $this->assertArrayNotHasKey('work_price_kopecks', (array) $response->json('items_to_review.0'));
        // Несопоставленные работы — без мусора разбора и без ссылок на тендеры.
        $this->assertSame([['name' => 'Монтаж секции шинопровода', 'unit' => 'шт', 'count' => 1]], $response->json('unmatched_lines'));
    }

    public function test_prices_page_shows_market_sources(): void
    {
        PriceItem::factory()->create([
            'name' => 'Тестовый токосъёмник', 'unit' => 'шт', 'work_price_kopecks' => null, 'gesn_code' => null,
            'market_sources' => [['title' => 'Прайс подрядчика', 'url' => 'https://a.example.test/price', 'price_kopecks' => 300_000]],
            'market_checked_at' => '2026-11-25',
        ]);

        $this->actingAs(User::factory()->admin()->create())->get('/prices')
            ->assertOk()
            ->assertSee('рынок ≈ 3000 ₽/шт')
            ->assertSee('Максимальная цена записывается в вашу')
            ->assertSee('https://a.example.test/price')
            ->assertSee('проверено 25.11.2026');
    }
}
