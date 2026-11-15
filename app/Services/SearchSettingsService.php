<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Region;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Что ищем: включённые регионы и порог суммы контракта.
 */
class SearchSettingsService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return Collection<int, Region>
     */
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get();
    }

    /** Общий порог для экрана — наименьший среди включённых регионов. */
    public function minAmountKopecks(): int
    {
        return (int) (Region::query()->where('is_enabled', true)->min('min_amount_kopecks')
            ?? Region::query()->min('min_amount_kopecks')
            ?? 0);
    }

    /**
     * @param  list<string>  $enabledCodes
     */
    public function apply(array $enabledCodes, int $minAmountKopecks): void
    {
        $this->db->transaction(function () use ($enabledCodes, $minAmountKopecks): void {
            Region::query()->update(['min_amount_kopecks' => $minAmountKopecks, 'is_enabled' => false]);
            Region::query()->whereIn('code', $enabledCodes)->update(['is_enabled' => true]);
        });
    }
}
