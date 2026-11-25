<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Работы, добавленные в расчёт вручную (например, по ТЗ без таблицы объёмов),
 * не привязаны к файлу: у такой позиции estimate_file_id пустой.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('estimate_file_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('estimate_lines')->whereNull('estimate_file_id')->delete();

        Schema::table('estimate_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('estimate_file_id')->nullable(false)->change();
        });
    }
};
