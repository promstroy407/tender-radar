<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Нормы ГЭСН из ФСНБ-2022: затраты труда на единицу нормы (без материалов).
        Schema::create('gesn_norms', function (Blueprint $table): void {
            $table->string('code', 20)->primary();
            $table->string('kind', 16);
            $table->string('table_name');
            $table->text('name');
            $table->string('unit', 32);
            // Затраты труда: [{"code": "1-100-38", "milli": 9280}] — тысячные доли чел.-ч.
            $table->json('labor');
            $table->timestamps();
        });

        // Сметные цены затрат труда рабочих по регионам за последний опубликованный квартал.
        Schema::create('labor_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('region_code', 2);
            $table->foreign('region_code')->references('code')->on('regions')->cascadeOnDelete();
            $table->string('code', 16);
            $table->unsignedInteger('rate_kopecks');
            $table->string('period', 32);
            $table->timestamps();

            $table->unique(['region_code', 'code']);
        });

        Schema::table('price_items', function (Blueprint $table): void {
            // Норма ГЭСН, по которой считается нормативная цена позиции.
            $table->string('gesn_code', 20)->nullable()->after('work_price_kopecks');
        });
    }

    public function down(): void
    {
        Schema::table('price_items', function (Blueprint $table): void {
            $table->dropColumn('gesn_code');
        });
        Schema::dropIfExists('labor_rates');
        Schema::dropIfExists('gesn_norms');
    }
};
