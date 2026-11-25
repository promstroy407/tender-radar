<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Рыночная цена позиции — средняя по открытым прайсам, отдельно от вашей цены
 * (решение заказчика 25.11.2026). Хранятся сами цены источников с адресами и дата
 * проверки; средняя считается из них, не хранится. Считается по ней, только если
 * нет ни вашей цены, ни нормы ГЭСН с загруженными ставками.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_items', function (Blueprint $table): void {
            // [{"title": "…", "url": "https://…", "price_kopecks": 100000, "note": "…"}]
            $table->json('market_sources')->nullable();
            $table->date('market_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('price_items', function (Blueprint $table): void {
            $table->dropColumn(['market_sources', 'market_checked_at']);
        });
    }
};
