<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Запасной режим Telegram (TELEGRAM_UPDATES_MODE=polling); в режиме webhook команда сразу выходит.
Schedule::command('telegram:poll')->everyMinute()->withoutOverlapping();
