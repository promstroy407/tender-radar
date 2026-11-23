<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Eis\EisException;
use App\Services\Eis\EisImporter;
use App\Services\Eis\EisImportLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Сбор контрактов одного региона за один день — в фоне: у Москвы за день сотни файлов.
 */
class ImportEisDay implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 2;

    public int $backoff = 300;

    public function __construct(
        public readonly string $regionCode,
        public readonly string $date,
    ) {}

    public function uniqueId(): string
    {
        return $this->regionCode.'|'.$this->date;
    }

    public function handle(EisImporter $importer, EisImportLog $log): void
    {
        $date = Carbon::parse($this->date);

        try {
            $log->recordSuccess($importer->importDay($this->regionCode, $date));
        } catch (EisException $e) {
            // Ошибка ЕИС (нет токена, сбой сервиса) — в журнал; повтор — по расписанию.
            $log->recordError($this->regionCode, $date, $e->getMessage());
        }
    }
}
