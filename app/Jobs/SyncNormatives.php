<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Normatives\NormativesSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Загрузка нормативов из ФГИС ЦС в фоне: около тысячи запросов, несколько минут. */
class SyncNormatives implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function handle(NormativesSync $sync): void
    {
        $sync->run();
    }
}
