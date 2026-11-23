<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Запасной режим Telegram (TELEGRAM_UPDATES_MODE=polling); в режиме webhook команда сразу выходит.
// Опрос длится до 35 секунд. Метка «уже идёт» живёт 2 минуты, а не сутки: если опрос
// оборвался (перезапуск при выкладке), бот не замолкает до завтра.
Schedule::command('telegram:poll')->everyMinute()->withoutOverlapping(2);

// Нормативы ГЭСН и ставки труда (ФГИС ЦС публикует их поквартально): раз в неделю, ночью.
Schedule::command('normatives:sync --if-stale=7')->dailyAt('03:17')->withoutOverlapping();

// Сбор контрактов из ЕИС за последние 3 дня: ЕИС публикует с задержкой, повтор не дублирует.
Schedule::command('eis:import --days=3')->dailyAt('05:41')->withoutOverlapping(30);
