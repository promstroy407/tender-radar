<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ContractSearch;
use App\Enums\ContractFilter;
use App\Enums\ContractKind;
use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ContractService
{
    /**
     * Список контрактов для главного экрана: новые сверху, горячие — с фильтром «Горячие».
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function search(ContractSearch $search): LengthAwarePaginator
    {
        return $this->query($search)
            ->with(['company', 'region'])
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->paginate($search->perPage);
    }

    /**
     * Сколько контрактов под каждым быстрым фильтром — для подписей на кнопках.
     *
     * @return array<string, int> значение фильтра => количество
     */
    public function counts(ContractSearch $search): array
    {
        $result = [];
        foreach (ContractFilter::cases() as $filter) {
            $result[$filter->value] = $this->query($search->withFilter($filter))->count();
        }

        return $result;
    }

    /** Самый свежий контракт — образец для предпросмотра шаблона КП. */
    public function latest(): ?Contract
    {
        return Contract::query()->with(['company', 'region'])
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return Builder<Contract>
     */
    private function query(ContractSearch $search): Builder
    {
        $query = Contract::query()
            ->whereHas('region', function (Builder $region) use ($search): void {
                $region->where('is_enabled', true)
                    // Порог суммы задаётся для региона в «Настройках».
                    ->whereColumn('contracts.amount_kopecks', '>=', 'regions.min_amount_kopecks');

                if ($search->regionCodes !== []) {
                    $region->whereIn('code', $search->regionCodes);
                }
            });

        if ($search->minAmountKopecks !== null) {
            $query->where('amount_kopecks', '>=', $search->minAmountKopecks);
        }

        if ($search->onlyNew) {
            $query->whereDoesntHave('offer');
        }

        match ($search->filter) {
            ContractFilter::All => null,
            ContractFilter::Hot => $query->hot(),
            ContractFilter::Profile => $query->where('kind', ContractKind::Profile),
            ContractFilter::General => $query->where('kind', ContractKind::General),
        };

        return $query;
    }
}
