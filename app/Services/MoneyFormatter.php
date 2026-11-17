<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Форматирует суммы, хранящиеся в копейках, без перехода к float.
 */
class MoneyFormatter
{
    private const NBSP = "\u{00A0}";

    public function format(int $kopecks): string
    {
        $sign = $kopecks < 0 ? '−' : '';
        $abs = abs($kopecks);

        $rubles = intdiv($abs, 100);
        $rest = $abs % 100;

        $result = $sign.$this->groupThousands($rubles);

        if ($rest !== 0) {
            $result .= ','.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
        }

        return $result.self::NBSP.'₽';
    }

    /** Для поля ввода: «1500000» или «1500000,50»; пусто, если суммы нет. */
    public function input(?int $kopecks): string
    {
        if ($kopecks === null) {
            return '';
        }

        $rest = abs($kopecks) % 100;
        $rubles = ($kopecks < 0 ? '-' : '').intdiv(abs($kopecks), 100);

        return $rest === 0 ? $rubles : $rubles.','.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }

    private function groupThousands(int $value): string
    {
        $digits = (string) $value;
        $groups = [];

        while (strlen($digits) > 3) {
            array_unshift($groups, substr($digits, -3));
            $digits = substr($digits, 0, -3);
        }

        array_unshift($groups, $digits);

        return implode(self::NBSP, $groups);
    }
}
