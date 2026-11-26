<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PriceSection;
use App\Models\PriceItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Рыночные цены из database/data/market_prices.json — их раз в день дополняет задача
 * поиска цен (2–4 открытых прайса, с адресами). Позиции, которой ещё нет в прайсе,
 * создаётся (с ключевыми словами из файла).
 *
 * Решение заказчика 26.11.2026: максимальная рыночная цена записывается в вашу цену,
 * если она выше («чтобы не работать в минус»). Делается это только при новой проверке
 * (дата в файле позже даты в базе): ручная правка цены держится до следующей проверки
 * этой позиции. Запускается при каждой выкладке (db:seed), повтор ничего не меняет.
 */
class MarketPriceSeeder extends Seeder
{
    public const FILE = 'data/market_prices.json';

    public function run(): void
    {
        $path = database_path(self::FILE);
        if (! is_file($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $entries = is_array($data) && is_array($data['items'] ?? null) ? $data['items'] : [];

        foreach ($entries as $entry) {
            $parsed = is_array($entry) ? $this->parse($entry) : null;
            if ($parsed === null) {
                Log::warning('Рыночная цена пропущена: запись неполная.', ['entry' => $entry]);

                continue;
            }

            $item = PriceItem::query()->firstOrNew(['name' => $parsed['name'], 'unit' => $parsed['unit']]);
            if (! $item->exists) {
                $item->fill(['section' => $parsed['section'], 'keywords' => $parsed['keywords'], 'is_active' => true]);
            }
            if ($item->exists && $item->market_checked_at !== null && $item->market_checked_at->toDateString() >= $parsed['checked_at']) {
                continue;
            }

            $item->fill(['market_sources' => $parsed['sources'], 'market_checked_at' => $parsed['checked_at']]);
            $max = $item->marketPriceKopecks();
            if ($max !== null && ($item->work_price_kopecks === null || $item->work_price_kopecks < $max)) {
                $item->work_price_kopecks = $max;
            }
            $item->save();
        }
    }

    /**
     * @param  array<mixed>  $entry
     * @return array{name: string, unit: string, section: PriceSection, keywords: string, checked_at: string, sources: list<array{title: string, url: string, price_kopecks: int, note?: string}>}|null
     */
    private function parse(array $entry): ?array
    {
        $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';
        $unit = is_string($entry['unit'] ?? null) ? $entry['unit'] : '';
        $section = is_string($entry['section'] ?? null) ? PriceSection::tryFrom($entry['section']) : null;
        $keywords = is_string($entry['keywords'] ?? null) ? trim($entry['keywords']) : '';
        $checkedAt = is_string($entry['checked_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['checked_at']) === 1 ? $entry['checked_at'] : null;

        $sources = [];
        foreach (is_array($entry['sources'] ?? null) ? $entry['sources'] : [] as $source) {
            if (! is_array($source)) {
                continue;
            }
            $title = is_string($source['title'] ?? null) ? trim($source['title']) : '';
            $url = is_string($source['url'] ?? null) ? $source['url'] : '';
            $price = $source['price_kopecks'] ?? null;
            if ($title === '' || ! str_starts_with($url, 'https://') || ! is_int($price) || $price <= 0) {
                continue;
            }
            $sources[] = array_filter([
                'title' => mb_substr($title, 0, 255),
                'url' => mb_substr($url, 0, 1000),
                'price_kopecks' => $price,
                'note' => is_string($source['note'] ?? null) ? mb_substr($source['note'], 0, 255) : null,
            ], fn (mixed $value): bool => $value !== null);
        }

        if ($name === '' || mb_strlen($name) > 255 || ! in_array($unit, ['м', 'шт', 'компл', 'точка', 'услуга'], true)
            || $section === null || $keywords === '' || $checkedAt === null || $sources === []) {
            return null;
        }

        /** @var list<array{title: string, url: string, price_kopecks: int, note?: string}> $sources */
        return ['name' => $name, 'unit' => $unit, 'section' => $section, 'keywords' => $keywords, 'checked_at' => $checkedAt, 'sources' => $sources];
    }
}
