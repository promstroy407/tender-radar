<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EstimateFile;
use App\Services\Estimates\EstimateParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Разбор файла сметы в фоне: большие PDF и Excel читаются дольше одного запроса. */
class ParseEstimateFile implements ShouldQueue
{
    use Queueable;

    // Скан сметы распознаётся постранично — до получаса на большой файл.
    public int $timeout = 1500;

    public int $tries = 1;

    public function __construct(
        public readonly EstimateFile $file,
    ) {}

    public function handle(EstimateParser $parser): void
    {
        $parser->parse($this->file);
    }
}
