<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Документация закупки (проект, смета, ТЗ) из извещения ЕИС: в реестре контрактов её нет,
 * а в контракте есть номер извещения. Файлы не храним — только список со ссылками;
 * скачиваются в смету по кнопке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('notification_number', 32)->nullable()->after('registry_number');
            // Когда последний раз искали документацию: отличает «не искали» от «файлов нет».
            $table->timestamp('documents_checked_at')->nullable();
            $table->index('notification_number');
        });

        Schema::create('contract_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('url', 1000);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_documents');

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex(['notification_number']);
            $table->dropColumn(['notification_number', 'documents_checked_at']);
        });
    }
};
