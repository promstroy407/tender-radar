<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/**
 * Черновик работ по тексту технического задания, где объёмы не сведены в таблицу,
 * а описаны строками: «Шинопровод: … Длина шинопровода – 113 м», «Рубильник: … Количество – 2 шт.».
 *
 * Правила:
 * - «…существующего оборудования…» включает демонтаж, «…нового оборудования…» — монтаж;
 *   пока ни того ни другого не было (описание объекта), объёмы не берём;
 * - короткий абзац с двоеточием в конце («Питающий кабель:») — что именно монтируем;
 * - «Длина … – 92 м», «Количество … – 2 шт» — объём; первая длина на заголовок, остальные —
 *   её части («в том числе»), их пропускаем;
 * - характеристики (проводники, напряжение, мощность, потребители) — не объём работ.
 *
 * Это черновик: каждая строка видна в расчёте, её можно сопоставить с прайсом или не считать.
 */
class TzDraftExtractor
{
    private const DEMOLITION = '/существующ/u';

    private const MONTAGE = '/(^|\s)(нов(ое|ого|ым|ых|ый)|требования к нов)/u';

    /** «Длина шинопровода – 114 м (96 м основной + 18 м ремонтный)», «Количество – 2 шт.» */
    private const QUANTITY = '/^(длина|количество|протяж[её]нность|объ[её]м)\b([^–—:-]*)[–—:-]\s*(?:около|не менее|до|ориентировочно|~)?\s*(\d+(?:[.,]\d+)?)(?![-.,]?\d)(?!\s*-)\s*([^\s(;,.]*)/u';

    /** Характеристики, а не объём работ. */
    private const NOT_VOLUME = '/^(проводник|фаз|напряжени|мощност|ток\b|потребител|скорост|падени|температур|радиус|ремонтн\w* зон)/u';

    /**
     * @param  list<string>  $paragraphs
     * @return list<ExtractedLine>
     */
    public function extract(array $paragraphs): array
    {
        $mode = null;
        $heading = null;
        $headingHasLength = false;
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $text = trim($paragraph);
            $lower = mb_strtolower($text);
            if ($text === '') {
                continue;
            }

            // Смена режима: заголовок раздела или пункт ТЗ про существующее/новое оборудование.
            if (mb_strlen($text) <= 80 && preg_match(self::DEMOLITION, $lower) === 1) {
                $mode = 'Демонтаж';
                $heading = null;

                continue;
            }
            if (mb_strlen($text) <= 80 && preg_match(self::MONTAGE, $lower) === 1) {
                $mode = 'Монтаж';
                $heading = null;

                continue;
            }
            if (preg_match('/^характеристик/u', $lower) === 1) {
                $mode = null;
                $heading = null;

                continue;
            }

            // Заголовок того, что монтируем: короткий, с двоеточием в конце.
            if (str_ends_with($text, ':') && mb_strlen($text) <= 60 && ! str_starts_with($text, '-')) {
                $heading = rtrim($text, ': ');
                $headingHasLength = false;

                continue;
            }

            if ($mode === null || $heading === null) {
                continue;
            }

            if (preg_match(self::QUANTITY, $lower, $m) !== 1) {
                continue;
            }
            $label = trim($m[2]);
            if (preg_match(self::NOT_VOLUME, $label) === 1) {
                continue;
            }

            $isLength = in_array($m[1], ['длина', 'протяженность', 'протяжённость'], true);
            if ($isLength && $headingHasLength) {
                continue;
            }

            $quantity = (int) round((float) str_replace(',', '.', $m[3]) * 1000);
            if ($quantity <= 0) {
                continue;
            }
            $unit = $this->unit($m[4], $isLength);

            // «Длина шинопровода» дублирует заголовок «Шинопровод» — оставляем только заголовок.
            $detail = $label !== '' && ! $isLength ? ' — '.$m[1].' '.$label : '';
            $lines[] = new ExtractedLine(mb_substr($mode.': '.$heading.$detail, 0, 500), $unit, $quantity);

            if ($isLength) {
                $headingHasLength = true;
            }
        }

        return $lines;
    }

    private function unit(string $raw, bool $isLength): string
    {
        $raw = rtrim($raw, '.');

        return match (true) {
            str_starts_with($raw, 'км') => 'км',
            str_starts_with($raw, 'м') && ! str_starts_with($raw, 'мм') => 'м',
            $isLength => 'м',
            str_starts_with($raw, 'компл') => 'компл',
            default => 'шт',
        };
    }
}
