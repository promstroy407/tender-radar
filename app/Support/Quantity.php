<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Объём из формы («12,5», «114», «0.25») в тысячных долях единицы — без дробных чисел.
 */
final class Quantity
{
    /** До 9 цифр целой части и до трёх после запятой, больше нуля. */
    public const PATTERN = '/^(?!0+(?:[.,]0+)?$)\d{1,9}(?:[.,]\d{1,3})?$/';

    public static function toMilli(string $value): int
    {
        $value = str_replace(',', '.', trim($value));
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }
}
