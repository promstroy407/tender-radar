<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Итоги быстрой сметы: только работы (материалы считаются отдельно).
 * Работы складываются из нормативных смет заказчика и позиций, посчитанных по прайсу.
 * Всё в копейках, процент — в десятых долях.
 */
final readonly class EstimateSummary
{
    public function __construct(
        public int $normativeKopecks,
        public int $normativeFiles,
        public int $priceListKopecks,
        public int $contractKopecks,
        public int $linesTotal,
        public int $linesPriced,
        public int $linesUnmatched,
        public int $linesNoPrice,
        public int $linesSkipped,
    ) {}

    public function workKopecks(): int
    {
        return $this->normativeKopecks + $this->priceListKopecks;
    }

    /** Есть что показать: нормативная смета или позиции из ведомостей. */
    public function hasData(): bool
    {
        return $this->normativeFiles > 0 || $this->linesTotal > 0;
    }

    /** Работы от суммы контракта (она включает и материалы), в десятых процента (523 = 52,3 %). null — сравнивать не с чем. */
    public function shareOfContractPermille(): ?int
    {
        if ($this->contractKopecks <= 0 || $this->workKopecks() === 0) {
            return null;
        }

        return intdiv($this->workKopecks() * 1000 + intdiv($this->contractKopecks, 2), $this->contractKopecks);
    }

    public function shareLabel(): ?string
    {
        $permille = $this->shareOfContractPermille();

        return $permille === null ? null : intdiv($permille, 10).','.($permille % 10).' %';
    }
}
