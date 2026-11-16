<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Достаёт позиции (наименование, единица, количество, материал) из строк таблицы —
 * листа Excel или текста PDF, разбитого на колонки. Сначала ищет шапку таблицы,
 * без шапки — угадывает колонки по содержимому строки.
 */
class TableExtractor
{
    private const HEADER_SCAN_ROWS = 60;

    private const SKIP_PREFIXES = ['итого', 'всего', 'сумма', 'стоимость работ', 'раздел', 'в том числе', 'в т.ч', 'накладные', 'сметная прибыль', 'ндс', 'перевозка', 'материалы', 'оборудование', 'прочие', 'коэффициент', 'нр ', 'сп ', 'средний разряд', '% выполнения', 'объем=', 'индекс', 'зтр', 'от(зт)', 'эм ', 'в т. ч'];

    public function __construct(
        private readonly NumberParser $numbers,
        private readonly UnitNormalizer $units,
    ) {}

    /**
     * @param  list<list<string|int|float|null>>  $rows
     * @return list<ExtractedLine>
     */
    public function extract(array $rows): array
    {
        $header = $this->findHeader($rows);

        $lines = [];
        foreach ($rows as $index => $row) {
            if ($header !== null && $index <= $header['row']) {
                continue;
            }

            $line = $header !== null ? $this->byHeader($row, $header) : $this->byGuess($row);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     * @return array{row: int, name: int, unit: int|null, qty: int, material: int|null}|null
     */
    private function findHeader(array $rows): ?array
    {
        foreach (array_slice($rows, 0, self::HEADER_SCAN_ROWS, true) as $index => $row) {
            $name = $unit = $qty = $material = null;

            foreach ($row as $col => $cell) {
                $text = $this->normalize((string) $cell);
                if ($text === '') {
                    continue;
                }
                if ($name === null && str_contains($text, 'наименован')) {
                    $name = $col;
                } elseif ($unit === null && (preg_match('/(^|\s)(ед|еи)(\s|\.|$|иниц)/u', $text) === 1 || str_contains($text, 'изм'))) {
                    $unit = $col;
                } elseif ($qty === null && (str_contains($text, 'кол-во') || str_contains($text, 'количеств') || preg_match('/(^|\s)кол\./u', $text) === 1)) {
                    $qty = $col;
                } elseif ($material === null && str_contains($text, 'материал')) {
                    $material = $col;
                }
            }

            if ($name !== null && $qty !== null) {
                return ['row' => $index, 'name' => $name, 'unit' => $unit, 'qty' => $qty, 'material' => $material];
            }
        }

        return null;
    }

    /**
     * @param  list<string|int|float|null>  $row
     * @param  array{row: int, name: int, unit: int|null, qty: int, material: int|null}  $header
     */
    private function byHeader(array $row, array $header): ?ExtractedLine
    {
        $name = $this->cleanName((string) ($row[$header['name']] ?? ''));
        $quantity = $this->numbers->toMilli($row[$header['qty']] ?? null);

        if ($name === null || $quantity === null) {
            return null;
        }

        $unit = $header['unit'] !== null ? trim((string) ($row[$header['unit']] ?? '')) : '';
        $material = $header['material'] !== null ? $this->numbers->toKopecks($row[$header['material']] ?? null) : null;

        return $this->line($name, $unit === '' ? null : $unit, $quantity, $material);
    }

    /**
     * Без шапки: наименование — самая длинная текстовая ячейка, единица — ячейка
     * вида «м»/«шт», количество — первое число после единицы.
     *
     * @param  list<string|int|float|null>  $row
     */
    private function byGuess(array $row): ?ExtractedLine
    {
        $name = null;
        $unitCol = null;

        foreach ($row as $col => $cell) {
            $text = trim((string) $cell);
            if ($unitCol === null && $this->units->looksLikeUnit($text)) {
                $unitCol = $col;

                continue;
            }
            if (preg_match('/\p{L}/u', $text) === 1 && mb_strlen($text) >= 5 && ($name === null || mb_strlen($text) > mb_strlen($name))) {
                $name = $text;
            }
        }

        if ($unitCol === null || $name === null) {
            return null;
        }

        foreach (array_slice($row, $unitCol + 1) as $cell) {
            $quantity = $this->numbers->toMilli($cell);
            if ($quantity !== null) {
                $clean = $this->cleanName($name);

                return $clean === null ? null : $this->line($clean, trim((string) $row[$unitCol]), $quantity, null);
            }
        }

        return null;
    }

    private function line(string $name, ?string $unit, int $quantityMilli, ?int $materialKopecks): ?ExtractedLine
    {
        if ($this->units->isService($unit)) {
            return null;
        }

        // «100 м» × 1,255 = 125,5 м: переводим в единицы прайса.
        $parsed = $this->units->parse($unit);
        if ($parsed !== null && $parsed['multiplier'] > 1) {
            $quantityMilli *= $parsed['multiplier'];
            $unit = $parsed['kind'] === UnitNormalizer::LENGTH ? 'м' : 'шт';
        }

        return new ExtractedLine($name, $unit, $quantityMilli, $materialKopecks);
    }

    private function cleanName(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        // Номер позиции в начале «12.», «3)» убираем.
        $name = trim(preg_replace('/^\d+(\.\d+)*[.)]?\s+/u', '', $name) ?? '');

        if (mb_strlen($name) < 3 || preg_match('/\p{L}/u', $name) !== 1) {
            return null;
        }

        $lower = $this->normalize($name);
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        return mb_substr($name, 0, 1000);
    }

    private function normalize(string $text): string
    {
        return trim(str_replace('ё', 'е', mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? '')));
    }
}
