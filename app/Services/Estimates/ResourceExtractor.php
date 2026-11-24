<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Достаёт из нормативной сметы заказчика (форма 421/пр, выгрузки ГРАНД, РИК,
 * «Строительный эксперт») строки ресурсов — материалы и оборудование:
 * номер позиции, шифр ФСБЦ («20.2.05.04-0065») или текущей цены («ТЦ_…»),
 * наименование, единица, количество и стоимость. Стоимость — последнее число
 * строки (графа «всего в текущем уровне цен»), количество — первое после единицы.
 */
class ResourceExtractor
{
    private const CODE = '/^(ТЦ_|ФССЦ|ТССЦ|ФСБЦ|СЦМ|КА[-_]|\d{2}\.\d\.\d{2}\.\d{2}-\d{4})/u';

    public function __construct(
        private readonly NumberParser $numbers,
        private readonly UnitNormalizer $units,
    ) {}

    /**
     * Ресурсы берутся с первого листа, где они есть: дальше обычно ведомость
     * ресурсов с теми же строками — второй раз их не считаем.
     *
     * @param  list<list<list<string|int|float|null>>>  $tables
     * @return list<ExtractedMaterial>
     */
    public function extract(array $tables): array
    {
        foreach ($tables as $rows) {
            $found = [];
            foreach ($rows as $row) {
                $material = $this->row($row);
                if ($material !== null) {
                    $found[] = $material;
                }
            }
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    /**
     * @param  list<string|int|float|null>  $row
     */
    private function row(array $row): ?ExtractedMaterial
    {
        // Значимые ячейки по порядку; перенос строки внутри ячейки — отдельные значения.
        $cells = [];
        foreach ($row as $cell) {
            foreach (explode("\n", (string) $cell) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $cells[] = $part;
                }
            }
        }

        if (count($cells) < 5 || preg_match('/^\d+$/', $cells[0]) !== 1 || preg_match(self::CODE, $cells[1]) !== 1) {
            return null;
        }

        $name = $this->cleanName($cells[2]);
        if ($name === null) {
            return null;
        }

        $unitAt = null;
        foreach (array_slice($cells, 3, null, true) as $index => $cell) {
            if ($this->units->looksLikeUnit($cell)) {
                $unitAt = $index;

                break;
            }
        }
        if ($unitAt === null) {
            return null;
        }

        $numbers = array_values(array_filter(
            array_slice($cells, $unitAt + 1),
            fn (string $cell): bool => $this->numbers->toMilli($cell) !== null,
        ));
        if (count($numbers) < 2) {
            return null;
        }

        $quantity = $this->numbers->toMilli($numbers[0]);
        $total = $this->numbers->toKopecks($numbers[count($numbers) - 1]);
        if ($quantity === null || $quantity <= 0 || $total === null || $total <= 0) {
            return null;
        }

        // «100 м» × 0,06 = 6 м; «10 шт» × 0,3 = 3 шт.
        $unit = $cells[$unitAt];
        $parsed = $this->units->parse($unit);
        if ($parsed !== null && $parsed['multiplier'] > 1) {
            $quantity *= $parsed['multiplier'];
            $unit = trim((string) preg_replace('/^\d+\s*/u', '', $unit));
        }

        return new ExtractedMaterial(mb_substr($cells[1], 0, 100), $name, mb_substr($unit, 0, 32), $quantity, $total);
    }

    /** Без хвоста расчёта цены: «Ценообразование: 82077,00/1,22*1,04236=70126,05», «(1750,00/1,22*1,04236)*10шт=…». */
    private function cleanName(string $name): ?string
    {
        $name = trim((string) preg_replace('/\s*Ценообразование:.*$/su', '', $name));
        $name = trim((string) preg_replace('/\s*\(?\d[\d\s,.]*\/\d[\d,.]*\*.*$/su', '', $name));
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return preg_match('/\p{L}{3,}/u', $name) === 1 ? mb_substr($name, 0, 1000) : null;
    }
}
