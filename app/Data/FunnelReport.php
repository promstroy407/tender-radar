<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DealSource;
use App\Enums\DealStatus;

/**
 * Отчёт «Воронка»: этапы, выходы, суммы и разбивка по источникам. Суммы — в копейках,
 * доли — в десятых долях процента (455 = 45,5 %), чтобы не было округлений float.
 */
final readonly class FunnelReport
{
    /**
     * @param  list<array{status: DealStatus, reached: int, now: int, fromPrevious: int|null, fromAll: int|null}>  $stages
     * @param  list<array{status: DealStatus, count: int}>  $exits
     * @param  list<array{source: DealSource, deals: int, contracts: int, contractKopecks: int}>  $sources
     */
    public function __construct(
        public int $total,
        public array $stages,
        public array $exits,
        public int $offerKopecks,
        public int $contractKopecks,
        public int $paidKopecks,
        public int $remainderKopecks,
        public array $sources,
    ) {}
}
