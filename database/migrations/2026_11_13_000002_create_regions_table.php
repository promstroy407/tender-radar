<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            // Код субъекта РФ: 77 — Москва, 16 — Татарстан и т.д.
            $table->string('code', 2)->primary();
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedBigInteger('min_amount_kopecks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
