<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            // Номер реестровой записи контракта в ЕИС.
            $table->string('registry_number', 32);
            $table->string('law', 8);
            $table->string('kind', 16);
            $table->string('region_code', 2);
            $table->text('subject');
            $table->string('customer_name');
            $table->string('customer_inn', 12)->nullable();
            $table->unsignedBigInteger('amount_kopecks');
            $table->date('signed_at');
            $table->date('ends_at')->nullable();
            $table->jsonb('sections')->default('[]');
            // Заказчик обязал привлечь субподрядчиков из СМП (ч. 5 ст. 30 44-ФЗ).
            $table->boolean('requires_smp_subcontract')->default(false);
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['law', 'registry_number']);
            $table->foreign('region_code')->references('code')->on('regions')->restrictOnDelete();
            $table->index('signed_at');
            $table->index(['region_code', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
