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
        $query = Contract::query()
            ->with(['company', 'region'])
            ->whereHas('region', function (Builder $region) use ($search): void {
                $region->where('is_enabled', true);

                if ($search->regionCodes !== []) {
                    $region->whereIn('code', $search->regionCodes);
                }
            });

        if ($search->minAmountKopecks !== null) {
            $query->where('amount_kopecks', '>=', $search->minAmountKopecks);
        }

        match ($search->filter) {
            ContractFilter::All => null,
            ContractFilter::Hot => $query->hot(),
            ContractFilter::Profile => $query->where('kind', ContractKind::Profile),
            ContractFilter::General => $query->where('kind', ContractKind::General),
        };

        return $query
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->paginate($search->perPage);
    }
}
