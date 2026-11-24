<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PriceSection;

/** Заполненная заранее форма новой позиции прайса. */
final readonly class PriceDraft
{
    public function __construct(
        public PriceSection $section,
        public string $name,
        public string $unit,
        public string $keywords,
    ) {}
}
