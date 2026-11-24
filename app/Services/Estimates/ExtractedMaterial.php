<?php

declare(strict_types=1);

namespace App\Services\Estimates;

/** Строка ресурса (материал, оборудование) из нормативной сметы. Количество — в натуральных единицах (м, шт). */
final readonly class ExtractedMaterial
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $unit,
        public int $quantityMilli,
        public int $totalKopecks,
    ) {}
}
