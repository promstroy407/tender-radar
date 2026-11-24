<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Находит в таблице сметы заказчика итоговые строки: «Всего по смете», материалы,
 * оборудование. Берётся последнее вхождение каждой строки — итог всей сметы
 * стоит в конце, после итогов разделов. Число — первое после подписи в той же строке.
 */
class NormativeTotalsExtractor
{
    /**
     * Подписи итога в порядке надёжности: «Итого по смете» в ПК РИК стоит после
     * «Компенсации НДС при УСН», поэтому оно — последнее средство.
     */
    private const TOTAL = [
        'всего - итоговая сметная стоимость', 'всего по смете', 'всего по локальному сметному расчету',
        'итого по локальному сметному расчету', 'всего по локальной смете', 'итого по локальной смете',
        'итого по смете',
    ];

    private const MATERIALS = [
        'всего - мр', 'всего мр', 'итого мр', 'материальные ресурсы', 'материалы',
        'всего материалы', 'итого материалы', 'в том числе материалы', 'в т ч материалы',
        'стоимость материалов', 'материалы подрядчика',
    ];

    private const EQUIPMENT = [
        'всего - оборудование', 'всего оборудование', 'итого оборудование', 'оборудование',
        'в том числе оборудование', 'в т ч оборудование', 'стоимость оборудования',
    ];

    public function __construct(
        private readonly NumberParser $numbers,
    ) {}

    /**
     * @param  list<list<list<string|int|float|null>>>  $tables
     */
    public function extract(array $tables): ?NormativeTotals
    {
        $total = $materials = $equipment = null;
        $totalRank = PHP_INT_MAX;

        foreach ($tables as $rows) {
            foreach ($rows as $row) {
                foreach ($row as $index => $cell) {
                    if (! is_string($cell)) {
                        continue;
                    }
                    $label = $this->normalize($cell);

                    // В форме 421/пр (ГРАНД-Смета) итог сметы — строка «Всего» после «Итоги по смете»;
                    // ловим только точное совпадение, иначе «Всего по позиции» тоже сошло бы.
                    $rank = $label === 'всего' ? count(self::TOTAL) : $this->rank($label, self::TOTAL);
                    if ($rank !== null) {
                        $value = $this->valueAfter($row, $index);
                        if ($value !== null && $rank <= $totalRank) {
                            $total = $value;
                            $totalRank = $rank;
                        }
                    } elseif ($this->matches($label, self::MATERIALS)) {
                        $materials = $this->valueAfter($row, $index) ?? $materials;
                    } elseif ($this->matches($label, self::EQUIPMENT)) {
                        $equipment = $this->valueAfter($row, $index) ?? $equipment;
                    } else {
                        continue;
                    }

                    // В строке одна подпись: копии справа (как в ПК РИК) не перечитываем.
                    break;
                }
            }
        }

        // Без строки материалов это может быть не смета, а любая таблица с «итого».
        if ($total === null || $materials === null || $materials + ($equipment ?? 0) > $total) {
            return null;
        }

        return new NormativeTotals($total, $materials, $equipment ?? 0);
    }

    /**
     * @param  list<string>  $labels
     */
    private function matches(string $label, array $labels): bool
    {
        return in_array($label, $labels, true);
    }

    /**
     * Место подписи итога в списке надёжности (по началу строки) или null.
     *
     * @param  list<string>  $labels
     */
    private function rank(string $label, array $labels): ?int
    {
        foreach ($labels as $rank => $candidate) {
            if (str_starts_with($label, $candidate)) {
                return $rank;
            }
        }

        return null;
    }

    /**
     * @param  list<string|int|float|null>  $row
     */
    private function valueAfter(array $row, int $index): ?int
    {
        foreach (array_slice($row, $index + 1) as $cell) {
            if ($cell === null || $cell === '') {
                continue;
            }
            $value = $this->numbers->toKopecks($cell);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalize(string $text): string
    {
        $text = str_replace('ё', 'е', mb_strtolower($text));
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? '';
        $text = str_replace(['т.ч.', 'т. ч.', ':'], ['т ч', 'т ч', ''], $text);

        return trim($text, " :.\t");
    }
}
