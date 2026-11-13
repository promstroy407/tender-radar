<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Рабочие регионы. Порог по умолчанию — 1 000 000 ₽ (в копейках).
 * Сидер идемпотентный: повторный запуск не сбрасывает настройки.
 */
class RegionSeeder extends Seeder
{
    private const MIN_AMOUNT_KOPECKS = 100_000_000;

    public function run(): void
    {
        $regions = [
            '77' => 'Москва',
            '50' => 'Московская область',
            '12' => 'Республика Марий Эл',
            '16' => 'Республика Татарстан',
            '21' => 'Чувашская Республика',
        ];

        foreach ($regions as $code => $name) {
            Region::query()->firstOrCreate(
                ['code' => (string) $code],
                ['name' => $name, 'is_enabled' => true, 'min_amount_kopecks' => self::MIN_AMOUNT_KOPECKS],
            );
        }
    }
}
