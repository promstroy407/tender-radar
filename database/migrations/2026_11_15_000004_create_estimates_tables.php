<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Быстрая смета по тендеру — для себя, чтобы понять цену. Одна на контракт.
        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Загруженные файлы документации: смета, ведомость, проект.
        Schema::create('estimate_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Позиции, найденные в файлах. Суммы «по-нашему» не хранятся — считаются по прайсу.
        Schema::create('estimate_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('estimate_file_id')->constrained('estimate_files')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('name');
            $table->string('unit', 32)->nullable();
            // Количество в тысячных долях единицы прайса (125,5 м = 125500): без дробных чисел.
            $table->unsignedBigInteger('quantity_milli');
            // Стоимость материала по смете заказчика (всего по строке), если она там есть.
            $table->unsignedBigInteger('material_kopecks')->nullable();
            $table->foreignId('price_item_id')->nullable()->constrained('price_items')->nullOnDelete();
            // auto — сопоставлено само, manual — выбрано вручную, skip — не считать.
            $table->string('match', 8);
            $table->timestamps();

            $table->index(['estimate_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_lines');
        Schema::dropIfExists('estimate_files');
        Schema::dropIfExists('estimates');
    }
};
