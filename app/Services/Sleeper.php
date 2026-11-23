<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Пауза между попытками. Отдельный сервис, чтобы тесты подменяли его и не ждали.
 */
class Sleeper
{
    public function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
