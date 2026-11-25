<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Models\PriceItem;
use Illuminate\Support\Collection;

/**
 * Сопоставляет позицию сметы с позицией нашего прайса по ключевым словам.
 * Побеждает самая конкретная фраза (больше совпавших букв); единицы должны
 * быть одного рода — метры к метрам, штуки к штукам. Демонтаж сопоставляется
 * только с позициями демонтажа, монтаж — только с монтажом: цены у них разные.
 */
class PriceMatcher
{
    public function __construct(
        private readonly UnitNormalizer $units,
    ) {}

    /**
     * @param  Collection<int, PriceItem>  $items
     */
    public function match(string $name, ?string $unit, Collection $items): ?PriceItem
    {
        $text = $this->normalize($name);
        $demolition = $this->isDemolition($name);
        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            if ($item->isDemolition() !== $demolition || ! $this->units->compatible($unit, $item->unit)) {
                continue;
            }

            foreach ($item->keywordList() as $phrase) {
                $score = $this->score($text, $phrase);
                if ($score > $bestScore) {
                    $best = $item;
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    /**
     * Все слова фразы должны встретиться в тексте как начало слова; короткие (до 3 букв:
     * «вру», «щр», «узо») — целым словом, чтобы «вру» не находилось во «вручную».
     * Счёт — общая длина совпавших слов.
     */
    private function score(string $text, string $phrase): int
    {
        $score = 0;
        foreach (explode(' ', $this->normalize($phrase)) as $word) {
            if ($word === '') {
                continue;
            }
            $end = mb_strlen($word) <= 3 ? '(\s|$)' : '';
            if (preg_match('/(^|\s)'.preg_quote($word, '/').$end.'/u', $text) !== 1) {
                return 0;
            }
            $score += mb_strlen($word);
        }

        return $score;
    }

    /**
     * Строка начинается с «Демонтаж», «Разборка», «Снятие» или содержит их в скобках после
     * работы («Извещатель… (Демонтаж извещателя)»). «Секция для снятия токосъёмника» — не демонтаж.
     */
    public function isDemolition(string $name): bool
    {
        $lower = str_replace('ё', 'е', mb_strtolower(trim($name)));

        return preg_match('/^(демонтаж|разборк|снятие)|\(\s*(демонтаж|разборк|снятие)/u', $lower) === 1;
    }

    private function normalize(string $text): string
    {
        $text = str_replace('ё', 'е', mb_strtolower($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
