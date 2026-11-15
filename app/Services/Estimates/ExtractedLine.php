<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/** Позиция, найденная в таблице сметы или ведомости. Количество — в единицах прайса (м, шт). */
final readonly class ExtractedLine
{
    public function __construct(
        public string $name,
        public ?string $unit,
        public int $quantityMilli,
        public ?int $materialKopecks = null,
    ) {}
}
