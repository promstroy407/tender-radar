<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\MaterialGroup;
use App\Models\EstimateMaterial;

/**
 * Раздел «Лоток и комплектующие» по сметам заказчика и сверка выборки с итогом смет.
 * Суммы — в копейках, по ценам сметы заказчика без НДС.
 */
final readonly class TrayReport
{
    /**
     * @param  list<array{group: MaterialGroup, rows: list<EstimateMaterial>, kopecks: int}>  $groups  группы раздела, только непустые
     * @param  int  $materialsKopecks  все материалы и оборудование, найденные в сметах
     * @param  int  $normativeKopecks  материалы + оборудование по итогам тех же смет — для сверки
     * @param  int  $filesToReread  нормативные сметы, прочитанные до выборки материалов
     */
    public function __construct(
        public array $groups,
        public int $materialsCount,
        public int $materialsKopecks,
        public int $normativeKopecks,
        public int $normativeFiles,
        public int $filesToReread,
    ) {}

    public function trayKopecks(): int
    {
        $sum = 0;
        foreach ($this->groups as $group) {
            $sum += $group['kopecks'];
        }

        return $sum;
    }

    /** Насколько выборка покрывает итог «материалы + оборудование», в десятых процента. */
    public function coveragePermille(): ?int
    {
        if ($this->normativeKopecks <= 0) {
            return null;
        }

        return intdiv($this->materialsKopecks * 1000 + intdiv($this->normativeKopecks, 2), $this->normativeKopecks);
    }
}
