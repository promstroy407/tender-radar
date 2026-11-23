<?php

declare(strict_types=1);

namespace App\Data\Eis;

use Illuminate\Support\Carbon;

/** Итог сбора одного региона за один день. */
final readonly class EisImportResult
{
    public function __construct(
        public string $regionCode,
        public Carbon $date,
        public int $archives,
        public int $files,
        public int $parsed,
        public int $matched,
        public int $saved,
    ) {}
}
