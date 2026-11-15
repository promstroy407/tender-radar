<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Числа из смет («1 234,5», «1234.50», 125.5 из Excel) — в целые тысячные доли
 * или копейки, без дробной арифметики.
 */
class NumberParser
{
    /** Количество в тысячных долях; null — не число или не больше нуля. */
    public function toMilli(string|int|float|null $value): ?int
    {
        return $this->scaled($value, 3);
    }

    /** Деньги в копейках; null — не число. */
    public function toKopecks(string|int|float|null $value): ?int
    {
        return $this->scaled($value, 2);
    }

    private function scaled(string|int|float|null $value, int $decimals): ?int
    {
        if ($value === null) {
            return null;
        }

        $text = is_float($value) ? sprintf('%.6F', $value) : (string) $value;
        $text = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text) ?? '';
        $text = rtrim($text, '₽рруб.');

        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $text) === 1 && substr_count($text, ',') + substr_count($text, '.') > 1) {
            // «1.234.567» или «1,234,567» — разделители тысяч.
            $text = str_replace([',', '.'], '', $text);
        }

        $lastComma = strrpos($text, ',');
        $lastDot = strrpos($text, '.');
        $separator = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

        if ($separator >= 0) {
            $whole = str_replace([',', '.'], '', substr($text, 0, $separator));
            $fraction = substr($text, $separator + 1);
        } else {
            $whole = $text;
            $fraction = '';
        }

        if ($whole === '' || preg_match('/^\d+$/', $whole) !== 1 || ($fraction !== '' && preg_match('/^\d+$/', $fraction) !== 1)) {
            return null;
        }

        if (strlen($whole) > 15) {
            return null;
        }

        // Округление «половина вверх» по следующему знаку после нужных.
        $fraction = str_pad($fraction, $decimals + 1, '0');
        $result = (int) $whole * (10 ** $decimals) + (int) substr($fraction, 0, $decimals);
        if ((int) $fraction[$decimals] >= 5) {
            $result++;
        }

        return $result > 0 ? $result : null;
    }
}
