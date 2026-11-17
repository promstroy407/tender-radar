<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * deals.received_at — обычный timestamp, как все даты в приложении: время в поясе
 * приложения (Москва). С timestamptz время заявки «2026-11-17T14:05+03:00» читалось
 * обратно как 14:05 UTC. Значения сохраняются: в timestamptz лежало московское время
 * с пометкой UTC, «AT TIME ZONE 'UTC'» возвращает его как есть.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE deals ALTER COLUMN received_at TYPE timestamp(0) without time zone USING received_at AT TIME ZONE 'UTC'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE deals ALTER COLUMN received_at TYPE timestamp(0) with time zone USING received_at AT TIME ZONE 'UTC'");
    }
};
