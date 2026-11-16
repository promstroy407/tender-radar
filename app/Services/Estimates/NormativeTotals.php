<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Итоги нормативной сметы заказчика (ГЭСН/ФСНБ, выгрузка ПК РИК, ГРАНД-Сметы и т. п.).
 * Стоимость работ не хранится, а вычисляется: итог минус материалы и оборудование.
 */
final readonly class NormativeTotals
{
    public function __construct(
        public int $totalKopecks,
        public int $materialsKopecks,
        public int $equipmentKopecks,
    ) {}

    /** Работы по нормативам: оплата труда, машины, накладные расходы и сметная прибыль. */
    public function worksKopecks(): int
    {
        return max(0, $this->totalKopecks - $this->materialsKopecks - $this->equipmentKopecks);
    }
}
