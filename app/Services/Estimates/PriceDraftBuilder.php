<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Data\PriceDraft;
use App\Enums\PriceSection;
use App\Http\Requests\PriceItemRequest;

/**
 * Черновик новой позиции прайса из непосчитанной строки сметы: раздел по смыслу,
 * единица прайса, ключевые слова — начала значимых слов наименования.
 */
class PriceDraftBuilder
{
    /** @var array<string, string> раздел => признак в наименовании */
    private const SECTIONS = [
        'fire' => '/пожар|извещат\w*\s+(?:дым|тепл|руч|пламен)|оповещ|соуэ|\bапс\b|табло/u',
        'video' => '/видео|камер/u',
        'access' => '/скуд|считыват|замок|турникет|кнопк\w*\s+выход|доступ/u',
        'security' => '/охран/u',
        'lowcurrent' => '/витая|\blan\b|\bскс\b|rj-?45|связ|телефон|коммутатор|патч/u',
    ];

    /** Слова, по которым позиции не различаются. */
    private const STOP_WORDS = ['для', 'или', 'при', 'без', 'под', 'над', 'тип', 'марка', 'монтаж', 'установка', 'прокладка'];

    public function build(string $name, ?string $unit): PriceDraft
    {
        $lower = mb_strtolower($name);

        $section = PriceSection::Electric;
        foreach (self::SECTIONS as $value => $pattern) {
            if (preg_match($pattern, $lower) === 1) {
                $section = PriceSection::from($value);

                break;
            }
        }

        return new PriceDraft(
            section: $section,
            name: mb_substr(trim(preg_replace('/\s+/u', ' ', $name) ?? $name), 0, 255),
            unit: $this->unit($unit),
            keywords: $this->keywords($lower),
        );
    }

    private function unit(?string $unit): string
    {
        $unit = mb_strtolower(trim((string) $unit, " .\t"));

        return match (true) {
            in_array($unit, PriceItemRequest::UNITS, true) => $unit,
            str_starts_with($unit, 'м') && ! str_contains($unit, '2') && ! str_contains($unit, '3') => 'м',
            str_starts_with($unit, 'компл') => 'компл',
            default => 'шт',
        };
    }

    /** «Резервируемый источник питания» → «резерв источн питан». */
    private function keywords(string $lower): string
    {
        preg_match_all('/[а-яё]{4,}/u', $lower, $m);

        $stems = [];
        foreach ($m[0] as $word) {
            if (in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            $stems[] = mb_substr($word, 0, 6);
            if (count($stems) === 3) {
                break;
            }
        }

        return implode(' ', $stems);
    }
}
