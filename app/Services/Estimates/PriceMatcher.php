<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Models\PriceItem;
use Illuminate\Support\Collection;

/**
 * Сопоставляет позицию сметы с позицией нашего прайса по ключевым словам.
 * Побеждает самая конкретная фраза (больше совпавших букв); единицы должны
 * быть одного рода — метры к метрам, штуки к штукам.
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
        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            if (! $this->units->compatible($unit, $item->unit)) {
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

    /** Все слова фразы должны встретиться в тексте (как начало слова); счёт — их общая длина. */
    private function score(string $text, string $phrase): int
    {
        $score = 0;
        foreach (explode(' ', $this->normalize($phrase)) as $word) {
            if ($word === '') {
                continue;
            }
            if (preg_match('/(^|\s)'.preg_quote($word, '/').'/u', $text) !== 1) {
                return 0;
            }
            $score += mb_strlen($word);
        }

        return $score;
    }

    private function normalize(string $text): string
    {
        $text = str_replace('ё', 'е', mb_strtolower($text));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';

        return trim($text);
    }
}
