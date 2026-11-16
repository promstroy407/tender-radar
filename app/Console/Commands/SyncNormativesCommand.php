<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncNormatives;
use App\Services\Normatives\NormativesSync;
use Illuminate\Console\Command;
use Illuminate\Contracts\Bus\Dispatcher;

/**
 * Обновление нормативов ГЭСН и ставок труда. По расписанию — в очередь;
 * --now выполняет сразу и печатает итог; --if-stale — только если давно не обновлялись.
 */
class SyncNormativesCommand extends Command
{
    protected $signature = 'normatives:sync {--now : Выполнить сразу, без очереди} {--if-stale=0 : Только если с прошлой загрузки прошло больше N дней}';

    protected $description = 'Загрузить нормы ГЭСН и сметные цены труда из ФГИС ЦС';

    public function handle(NormativesSync $sync, Dispatcher $bus): int
    {
        $days = (int) $this->option('if-stale');
        $last = $sync->lastStatus();
        if ($days > 0 && ($last['ok'] ?? false) === true && isset($last['finished_at'])
            && now()->diffInDays((string) $last['finished_at'], true) < $days) {
            $this->line('Нормативы свежие: '.$last['finished_at']);

            return self::SUCCESS;
        }

        if (! $this->option('now')) {
            $bus->dispatch(new SyncNormatives);
            $this->info('Загрузка нормативов поставлена в очередь.');

            return self::SUCCESS;
        }

        $status = $sync->run();
        if (($status['ok'] ?? false) !== true) {
            $this->error('Ошибка: '.(string) ($status['error'] ?? 'неизвестно'));

            return self::FAILURE;
        }

        $this->info('Норм: '.(string) $status['norms'].'; ставки: '.json_encode($status['periods'], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
