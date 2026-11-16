<?php

declare(strict_types=1);

namespace App\Services\Normatives;

use App\Models\GesnNorm;
use App\Models\LaborRate;
use App\Models\PriceItem;
use App\Services\Estimates\UnitNormalizer;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Нормативная цена работы по ресурсно-индексному методу, только труд:
 *   ОТ = Σ (чел.-ч нормы × сметная цена чел.-ч в регионе)
 *   цена = ОТ × (1 + НР% + СП%) / единиц прайса в единице нормы.
 * Машины и материалы не входят: машины в электромонтаже — около 2 % работ
 * (по смете ПК РИК заказчика), материалы считаются отдельно.
 * Округление до копейки — один раз, в конце.
 */
class NormativePricer
{
    /** @var array<string, GesnNorm|null> */
    private array $norms = [];

    /** @var array<string, array<string, LaborRate>> */
    private array $rates = [];

    public function __construct(
        private readonly Config $config,
        private readonly UnitNormalizer $units,
    ) {}

    public function price(PriceItem $item, string $region): ?NormativePrice
    {
        if ($item->gesn_code === null || $item->gesn_code === '') {
            return null;
        }

        $norm = $this->norm($item->gesn_code);
        if ($norm === null || $norm->labor === []) {
            return null;
        }

        $multiplier = $this->unitsPerNorm($norm->unit, $item->unit);
        $overheads = $this->config->get('normatives.overheads.'.$norm->kind);
        if ($multiplier === null || ! is_array($overheads)) {
            return null;
        }

        $rates = $this->rates($region);
        $sum = 0;
        $period = '';
        foreach ($norm->labor as $labor) {
            $rate = $rates[$labor['code']] ?? null;
            if ($rate === null) {
                return null;
            }
            $sum += $labor['milli'] * $rate->rate_kopecks;
            $period = $rate->period;
        }

        $nr = (int) $overheads['nr'];
        $sp = (int) $overheads['sp'];
        $denominator = 1000 * 100 * $multiplier;

        return new NormativePrice(
            kopecksPerUnit: intdiv($sum * (100 + $nr + $sp) + intdiv($denominator, 2), $denominator),
            normCode: $norm->code,
            period: $period,
            overheadPercent: $nr,
            profitPercent: $sp,
        );
    }

    /** Сколько единиц прайса в единице нормы: «100 м» → 100 для «м»; разные роды — null. */
    private function unitsPerNorm(string $normUnit, string $itemUnit): ?int
    {
        $norm = $this->units->parse($normUnit);
        $item = $this->units->parse($itemUnit);

        if ($norm === null || $item === null || $norm['kind'] !== $item['kind']) {
            return null;
        }

        return intdiv($norm['multiplier'], max(1, $item['multiplier'])) ?: null;
    }

    private function norm(string $code): ?GesnNorm
    {
        if (! array_key_exists($code, $this->norms)) {
            $this->norms[$code] = GesnNorm::query()->find($code);
        }

        return $this->norms[$code];
    }

    /**
     * @return array<string, LaborRate>
     */
    private function rates(string $region): array
    {
        return $this->rates[$region] ??= LaborRate::query()->where('region_code', $region)->get()->keyBy('code')->all();
    }
}
