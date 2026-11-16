<?php

declare(strict_types=1);

namespace App\Services\Normatives;

/** Нормативная цена работы за единицу прайса в регионе. */
final readonly class NormativePrice
{
    public function __construct(
        public int $kopecksPerUnit,
        public string $normCode,
        public string $period,
        public int $overheadPercent,
        public int $profitPercent,
    ) {}
}
