<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Models\PriceItem;
use App\Services\Normatives\NormativePrice;
use App\Services\Normatives\NormativePricer;

/**
 * Цена работы за единицу позиции прайса в регионе тендера: по нормативам
 * (ГЭСН × ставка труда региона + НР + СП), если у позиции есть норма и ставки
 * загружены; иначе — цена, вписанная вручную.
 */
class PriceResolver
{
    /** @var array<string, NormativePrice|null> */
    private array $normative = [];

    public function __construct(
        private readonly NormativePricer $pricer,
    ) {}

    public function unitPrice(PriceItem $item, string $region): ?int
    {
        return $this->normative($item, $region)->kopecksPerUnit ?? $item->work_price_kopecks;
    }

    public function normative(PriceItem $item, string $region): ?NormativePrice
    {
        $key = $item->id.'|'.$region;

        if (! array_key_exists($key, $this->normative)) {
            $this->normative[$key] = $this->pricer->price($item, $region);
        }

        return $this->normative[$key];
    }
}
