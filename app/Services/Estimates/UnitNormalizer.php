<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Единицы измерения из смет: «100 м», «шт.», «компл», «м.п.» — к нашим «м» и «шт»,
 * с множителем для укрупнённых единиц (в ГЭСН кабель считают сотнями метров).
 */
class UnitNormalizer
{
    public const LENGTH = 'length';

    public const PIECE = 'piece';

    /** Площадь, объём, масса и прочее: с метрами и штуками прайса не сопоставляются. */
    public const OTHER = 'other';

    /** Служебные строки сметы ГЭСН: трудозатраты, проценты НР/СП, коэффициенты. */
    public const SERVICE = 'service';

    /** Похоже ли значение ячейки на единицу измерения. */
    public function looksLikeUnit(string $value): bool
    {
        return $this->parse($value) !== null;
    }

    /**
     * @return array{kind: string, multiplier: int}|null
     */
    public function parse(?string $unit): ?array
    {
        $text = mb_strtolower(trim((string) $unit));
        $text = str_replace(['ё', ' '], ['е', ' '], $text);

        if ($text === '' || mb_strlen($text) > 20) {
            return null;
        }

        $multiplier = 1;
        if (preg_match('/^(\d+)\s*(.+)$/u', $text, $m) === 1) {
            $multiplier = max(1, (int) $m[1]);
            $text = trim($m[2]);
        }

        $text = rtrim($text, '. ');

        if (preg_match('/^(м|м\.?\s?п|п\.?\s?м|пог\.?\s?м|метр|м\s?пог)$/u', $text) === 1) {
            return ['kind' => self::LENGTH, 'multiplier' => $multiplier];
        }

        if ($text === 'км') {
            return ['kind' => self::LENGTH, 'multiplier' => $multiplier * 1000];
        }

        if (preg_match('/^(шт|штук|компл|комплект|к-т|кт|точк|точка|ед|услуг|услуга|система|прибор|место|порт|линия|шлейф|конец|концов|узел|отв|отверстие)$/u', $text) === 1) {
            return ['kind' => self::PIECE, 'multiplier' => $multiplier];
        }

        if (preg_match('/^(%|чел\.?\s?-?\s?ч|маш\.?\s?-?\s?ч|руб|коэф)$/u', $text) === 1) {
            return ['kind' => self::SERVICE, 'multiplier' => $multiplier];
        }

        if (preg_match('/^(м2|м3|м²|м³|кв\.?\s?м|куб\.?\s?м|т|тн|кг|г|л|га|км2)$/u', $text) === 1) {
            return ['kind' => self::OTHER, 'multiplier' => $multiplier];
        }

        return null;
    }

    /** Строка с такой единицей — служебная (НР, СП, трудозатраты), а не работа. */
    public function isService(?string $unit): bool
    {
        return ($this->parse($unit)['kind'] ?? null) === self::SERVICE;
    }

    /** Единица прайса («м», «шт», «компл», «точка», «услуга») — того же рода, что единица сметы? */
    public function compatible(?string $lineUnit, string $priceUnit): bool
    {
        $line = $this->parse($lineUnit);
        $price = $this->parse($priceUnit);

        return $line === null || $price === null || $line['kind'] === $price['kind'];
    }
}
