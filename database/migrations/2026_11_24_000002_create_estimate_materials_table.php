<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Материалы и оборудование из сметы заказчика — строки ресурсов (ФСБЦ, «ТЦ_…»)
 * с количеством и стоимостью по смете. Из них собирается раздел «Лоток и
 * комплектующие» и дальше — выборка материалов для КП на поставку.
 * Цена за единицу не хранится — считается из суммы и количества.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('estimate_file_id')->constrained('estimate_files')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('code', 100);
            $table->string('name', 1000);
            $table->string('unit', 32)->nullable();
            $table->bigInteger('quantity_milli');
            // Стоимость строки в текущих ценах сметы заказчика, без НДС.
            $table->bigInteger('total_kopecks');
            $table->timestamps();

            $table->index(['estimate_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_materials');
    }
};
