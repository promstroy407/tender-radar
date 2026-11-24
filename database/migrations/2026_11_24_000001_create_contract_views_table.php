<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Какие тендеры пользователь уже открывал: в ленте они бледнее и ниже,
 * чтобы не путаться в повторах. У каждого пользователя — свой список.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_views', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->primary(['user_id', 'contract_id']);
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_views');
    }
};
