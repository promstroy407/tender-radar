<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Настройки, которые администратор меняет в интерфейсе.
        // Значения шифруются ключом APP_KEY (он есть только в .env на сервере).
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
