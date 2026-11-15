<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Наше коммерческое предложение победителю по конкретному контракту.
        // Одно на контракт: повторная отправка обновляет запись.
        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->unique()->constrained('contracts')->cascadeOnDelete();
            $table->string('status', 16);
            // Канал, через который ушло КП; пусто — отметили «Не интересно», не отправляя.
            $table->string('channel', 16)->nullable();
            $table->timestamp('sent_at')->nullable();
            // Когда напомнить о себе, если победитель молчит.
            $table->date('remind_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['channel', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
