<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Калькулятор: смета, загруженная не из тендера (сторонняя). У такого расчёта нет
 * контракта — есть своё название и регион, по ставкам которого считаются работы.
 * У сметы тендера регион берётся из контракта и здесь не дублируется.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table): void {
            $table->unsignedBigInteger('contract_id')->nullable()->change();
            $table->string('name', 255)->nullable();
            $table->string('region_code', 2)->nullable();
            $table->foreign('region_code')->references('code')->on('regions')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE estimates ADD CONSTRAINT estimates_tender_or_calculation CHECK (
            (contract_id IS NOT NULL AND name IS NULL AND region_code IS NULL)
            OR (contract_id IS NULL AND name IS NOT NULL AND region_code IS NOT NULL))');
    }

    public function down(): void
    {
        DB::table('estimates')->whereNull('contract_id')->delete();
        DB::statement('ALTER TABLE estimates DROP CONSTRAINT estimates_tender_or_calculation');

        Schema::table('estimates', function (Blueprint $table): void {
            $table->dropForeign(['region_code']);
            $table->dropColumn(['name', 'region_code']);
            $table->unsignedBigInteger('contract_id')->nullable(false)->change();
        });
    }
};
