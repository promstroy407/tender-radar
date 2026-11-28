<?php

declare(strict_types=1);

use App\Services\ContractService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

/**
 * Однократная чистка по решению заказчика 28.11.2026: контракты, заключённые раньше
 * 30 дней назад, по которым нет сделки, КП и просчёта, удаляются. Дальше то же делает
 * ежедневная команда contracts:prune-stale, а сбор из ЕИС старые не берёт.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ContractService::class)->pruneStale(Carbon::now());
    }

    public function down(): void
    {
        // Удалённые контракты вернёт сбор из ЕИС, если они снова станут свежими (не станут).
    }
};
