<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Estimates\NumberParser;
use App\Services\Estimates\ResourceExtractor;
use App\Services\Estimates\UnitNormalizer;
use PHPUnit\Framework\TestCase;

class ResourceExtractorTest extends TestCase
{
    public function test_resources_from_stroy_expert_export(): void
    {
        // Строки как в выгрузке «Строительного эксперта» (тендер 2634): перенос строки внутри ячеек.
        $rows = [
            [47, 'ГЭСНм 08-02-390-01', null, 'Короба пластмассовые: шириной до 40 мм', '100 м', 10.06, null, 1234.5],
            [48, '20.2.05.04-0065', null, 'Короб кабельный (кабель-канал) ПВХ с крышкой, размеры 60х40 мм', '100 м', null, 0.06, "\n", 0.06, null, 13670.04, 1.24, 16950.85, "\n", "1017.05\n"],
            [27, 'ТЦ_89.1.61.02_77_9724019034_26.03.2026_02_18.1', 'Извещатель пожарный ручной адресный АКОР-ИПР (1750,00/1,22*1,04236)*10шт=14951,89', '10 шт', 2.5, 14951.89, 37379.73],
            [2, 'ТЦ_89.1.61.02_77_9724019034_26.03.2026_02_1.1', 'Прибор ППКОПУ «АКОР-496М» Ценообразование: 82077,00/1,22*1,04236=70126,05', 'шт', 1, 70126.05, 70126.05],
            [null, null, 'Итого по разделу', null, 999.0],
        ];
        // Второй лист — ведомость ресурсов с теми же строками: не дублируем.
        $tables = [$rows, $rows];

        $found = (new ResourceExtractor(new NumberParser, new UnitNormalizer))->extract($tables);

        $this->assertCount(3, $found);
        $this->assertSame('Короб кабельный (кабель-канал) ПВХ с крышкой, размеры 60х40 мм', $found[0]->name);
        $this->assertSame('м', $found[0]->unit);
        $this->assertSame(6_000, $found[0]->quantityMilli);
        $this->assertSame(101_705, $found[0]->totalKopecks);

        $this->assertSame('Извещатель пожарный ручной адресный АКОР-ИПР', $found[1]->name);
        $this->assertSame('шт', $found[1]->unit);
        $this->assertSame(25_000, $found[1]->quantityMilli);
        $this->assertSame(3_737_973, $found[1]->totalKopecks);

        $this->assertSame('Прибор ППКОПУ «АКОР-496М»', $found[2]->name);
        $this->assertSame(7_012_605, $found[2]->totalKopecks);
    }
}
