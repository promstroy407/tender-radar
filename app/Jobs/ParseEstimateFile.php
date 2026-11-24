<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EstimateFileStatus;
use App\Models\EstimateFile;
use App\Services\Estimates\EstimateParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Разбор файла сметы в фоне: большие PDF и Excel читаются дольше одного запроса. */
class ParseEstimateFile implements ShouldQueue
{
    use Queueable;

    // Скан сметы распознаётся постранично — до получаса на большой файл.
    public int $timeout = 1500;

    public int $tries = 1;

    /** Файл, который загрузил или перечитывает человек, — без очереди за автопросчётом. */
    public const URGENT_QUEUE = 'documents';

    public function __construct(
        public readonly EstimateFile $file,
        bool $urgent = false,
    ) {
        if ($urgent) {
            $this->onQueue(self::URGENT_QUEUE);
        }
    }

    public function handle(EstimateParser $parser): void
    {
        $parser->parse($this->file);
    }

    /** Задание оборвалось (перезапуск при выкладке, тайм-аут) — файл не должен висеть «читается». */
    public function failed(?Throwable $e): void
    {
        $this->file->forceFill([
            'status' => EstimateFileStatus::Failed,
            'error' => 'Чтение прервалось — нажмите «Прочитать заново».',
        ])->save();
    }
}
