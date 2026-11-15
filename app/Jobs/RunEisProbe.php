<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Eis\EisProbe;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Проверка ЕИС в фоне: запрос и ожидание архива могут занять до пары минут.
 */
class RunEisProbe implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function handle(EisProbe $probe): void
    {
        $probe->run();
    }
}
