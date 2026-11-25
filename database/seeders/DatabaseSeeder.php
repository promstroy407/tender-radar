<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Базовые справочники: регионы и стартовый прайс работ. Демо-данные — отдельно: `php artisan db:seed --class=DemoSeeder`.
     * Администратор создаётся командой `php artisan app:create-admin`, пароль в файлах не хранится.
     */
    public function run(): void
    {
        $this->call([RegionSeeder::class, PriceItemSeeder::class, MarketPriceSeeder::class]);
    }
}
