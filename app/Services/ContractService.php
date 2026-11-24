<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ContractSearch;
use App\Enums\ContractFilter;
use App\Enums\ContractKind;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ContractService
{
    /**
     * Список контрактов для главного экрана: новые сверху, горячие — с фильтром «Горячие».
     * Для пользователя ещё не открытые им — выше открытых.
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function search(ContractSearch $search): LengthAwarePaginator
    {
        $query = $this->query($search)->with(['company', 'region']);

        if ($search->viewerId !== null) {
            $viewer = $search->viewerId;
            $query->withExists(['viewers as viewed' => fn (Builder $users): Builder => $users->whereKey($viewer)])
                ->orderBy('viewed');
        }

        return $query
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

    /** Пользователь открыл тендер: в ленте он станет бледнее и уйдёт ниже. */
    public function markViewed(Contract $contract, User $user): void
    {
        $contract->viewers()->syncWithoutDetaching([$user->id => ['viewed_at' => Carbon::now()]]);
    }

    /** Самый свежий контракт — образец для предпросмотра шаблона КП. */
    public function latest(): ?Contract
    {
        return Contract::query()->with(['company', 'region'])
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->first();
    }

    /** Попадает ли контракт в ленту: регион включён и сумма не ниже порога региона. */
    public function isInFeed(Contract $contract): bool
    {
        $region = $contract->region;

        return $region->is_enabled && $contract->amount_kopecks >= $region->min_amount_kopecks;
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
            $query->whereDoesntHave('deal');
        }

        if ($search->onlyUnseen && $search->viewerId !== null) {
            $viewer = $search->viewerId;
            $query->whereDoesntHave('viewers', fn (Builder $users): Builder => $users->whereKey($viewer));
        }

        // Полезная документация загружается в просчёт сама — файлы просчёта и есть признак.
        if ($search->onlyWithDocuments) {
            $query->whereHas('estimate.files');
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
