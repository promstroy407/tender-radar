<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Достаёт из нормативной сметы заказчика (форма 421/пр, выгрузки ГРАНД, РИК,
 * «Строительный эксперт») работы (шифр ГЭСН/ФЕР/ТЕР) и строки ресурсов — материалы и оборудование:
 * номер позиции, шифр ФСБЦ («20.2.05.04-0065») или текущей цены («ТЦ_…»),
 * наименование, единица, количество и стоимость. Стоимость — последнее число
 * строки (графа «всего в текущем уровне цен»), количество — первое после единицы.
 */
class ResourceExtractor
{
    private const CODE = '/^(ТЦ_|ФССЦ|ТССЦ|ФСБЦ|СЦМ|КА[-_]|\d{2}\.\d\.\d{2}\.\d{2}-\d{4})/u';

    /** Шифр расценки на работу: ГЭСН, ФЕР, ТЕР (м — монтаж, п — пусконаладка, р — ремонт). */
    private const WORK_CODE = '/^(ГЭСН|ФЕР|ТЕР)[мпр]?\s?\d/u';

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
    public function materials(array $tables): array
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
     * Работы сметы — строки с шифром ГЭСН/ФЕР/ТЕР: наименование, единица, объём.
     * Их цену считаем сами, по нашему прайсу, — чтобы сравнить со сметой заказчика.
     * Берутся с первого листа, где они есть.
     *
     * @param  list<list<list<string|int|float|null>>>  $tables
     * @return list<ExtractedLine>
     */
    public function works(array $tables): array
    {
        foreach ($tables as $rows) {
            $found = [];
            foreach ($rows as $row) {
                $cells = $this->cells($row);
                if (count($cells) < 4 || preg_match('/^\d+$/', $cells[0]) !== 1 || preg_match(self::WORK_CODE, $cells[1]) !== 1) {
                    continue;
                }
                $name = $this->cleanName($cells[2]);
                $unitAt = $this->unitAt($cells);
                if ($name === null || $unitAt === null || $this->units->isService($cells[$unitAt])) {
                    continue;
                }
                $quantity = null;
                foreach (array_slice($cells, $unitAt + 1) as $cell) {
                    $quantity = $this->numbers->toMilli($cell);
                    if ($quantity !== null) {
                        break;
                    }
                }
                if ($quantity === null || $quantity <= 0) {
                    continue;
                }
                [$unit, $quantity] = $this->natural($cells[$unitAt], $quantity);
                $found[] = new ExtractedLine($name, $unit, $quantity);
            }
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    /**
     * Значимые ячейки по порядку; перенос строки внутри ячейки — отдельные значения.
     *
     * @param  list<string|int|float|null>  $row
     * @return list<string>
     */
    private function cells(array $row): array
    {
        $cells = [];
        foreach ($row as $cell) {
            foreach (explode("\n", (string) $cell) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $cells[] = $part;
                }
            }
        }

        return $cells;
    }

    /**
     * @param  list<string>  $cells
     */
    private function unitAt(array $cells): ?int
    {
        foreach (array_slice($cells, 3, null, true) as $index => $cell) {
            if ($this->units->looksLikeUnit($cell)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * «100 м» × 0,06 = 6 м; «10 шт» × 0,3 = 3 шт.
     *
     * @return array{string, int}
     */
    private function natural(string $unit, int $quantityMilli): array
    {
        $parsed = $this->units->parse($unit);
        if ($parsed !== null && $parsed['multiplier'] > 1) {
            return [trim((string) preg_replace('/^\d+\s*/u', '', $unit)), $quantityMilli * $parsed['multiplier']];
        }

        return [$unit, $quantityMilli];
    }

    /**
     * @param  list<string|int|float|null>  $row
     */
    private function row(array $row): ?ExtractedMaterial
    {
        $cells = $this->cells($row);

        if (count($cells) < 5 || preg_match('/^\d+$/', $cells[0]) !== 1 || preg_match(self::CODE, $cells[1]) !== 1) {
            return null;
        }

        $name = $this->cleanName($cells[2]);
        if ($name === null) {
            return null;
        }

        $unitAt = $this->unitAt($cells);
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

        [$unit, $quantity] = $this->natural($cells[$unitAt], $quantity);

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
