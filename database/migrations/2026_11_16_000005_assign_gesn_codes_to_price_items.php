<?php

declare(strict_types=1);

use Database\Seeders\PriceItemSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Привязка норм ГЭСН к позициям стартового прайса, уже лежащим в базе.
 * Позиции, которым норму выбрали вручную, не трогаем.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (PriceItemSeeder::GESN as $name => $code) {
            DB::table('price_items')->where('name', $name)->whereNull('gesn_code')->update(['gesn_code' => $code]);
        }
    }

    public function down(): void
    {
        foreach (PriceItemSeeder::GESN as $name => $code) {
            DB::table('price_items')->where('name', $name)->where('gesn_code', $code)->update(['gesn_code' => null]);
        }
    }
};
