<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Наш прайс работ: по нему считается быстрая смета тендера.
        Schema::create('price_items', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 16);
            $table->string('name');
            $table->string('unit', 16);
            // Цена работы за единицу; пусто — цену ещё не задали.
            $table->unsignedBigInteger('work_price_kopecks')->nullable();
            // Слова, по которым позиция сметы узнаётся как эта работа (через запятую).
            $table->text('keywords');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
