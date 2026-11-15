<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/** Итоги быстрой сметы. Всё в копейках, процент — в десятых долях. */
final readonly class EstimateSummary
{
    public function __construct(
        public int $workKopecks,
        public int $materialKopecks,
        public int $contractKopecks,
        public int $linesTotal,
        public int $linesPriced,
        public int $linesUnmatched,
        public int $linesNoPrice,
        public int $linesSkipped,
    ) {}

    public function totalKopecks(): int
    {
        return $this->workKopecks + $this->materialKopecks;
    }

    /** Наша цена от суммы контракта, в десятых процента (523 = 52,3 %). null — сравнивать не с чем. */
    public function shareOfContractPermille(): ?int
    {
        if ($this->contractKopecks <= 0 || $this->totalKopecks() === 0) {
            return null;
        }

        return intdiv($this->totalKopecks() * 1000 + intdiv($this->contractKopecks, 2), $this->contractKopecks);
    }

    public function shareLabel(): ?string
    {
        $permille = $this->shareOfContractPermille();

        return $permille === null ? null : intdiv($permille, 10).','.($permille % 10).' %';
    }
}
