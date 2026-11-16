<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Итоги нормативной сметы заказчика, если файл — такая смета. Стоимость работ
     * (итог − материалы − оборудование) не хранится, а вычисляется.
     */
    public function up(): void
    {
        Schema::table('estimate_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('norm_total_kopecks')->nullable();
            $table->unsignedBigInteger('norm_materials_kopecks')->nullable();
            $table->unsignedBigInteger('norm_equipment_kopecks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('estimate_files', function (Blueprint $table): void {
            $table->dropColumn(['norm_total_kopecks', 'norm_materials_kopecks', 'norm_equipment_kopecks']);
        });
    }
};
