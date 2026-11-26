<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Models\PriceItem;
use App\Services\Normatives\NormativePrice;
use App\Services\Normatives\NormativePricer;

/**
 * Цена работы за единицу позиции прайса в регионе тендера (решение заказчика 26.11.2026):
 * ваша цена (вписанная вручную или записанная из рынка); если её нет — по нормативам
 * (ГЭСН × ставка труда региона + НР + СП); если и норм нет — максимальная рыночная.
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
        return $item->work_price_kopecks ?? $this->normative($item, $region)->kopecksPerUnit ?? $item->marketPriceKopecks();
    }

    /** Цена взята с рынка: ни вашей цены, ни нормы нет. */
    public function isMarket(PriceItem $item, string $region): bool
    {
        return $this->normative($item, $region) === null && $item->work_price_kopecks === null && $item->marketPriceKopecks() !== null;
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
