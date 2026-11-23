<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Jobs\ImportEisDay;
use App\Models\Region;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * Ставит в очередь сбор контрактов: по каждому включённому региону за каждый день.
 */
class EisImportPlanner
{
    /** Больше не нужно: старые контракты победителям уже неинтересны. */
    public const MAX_DAYS = 60;

    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    /**
     * @param  Carbon  $until  последний день (включительно)
     * @param  list<string>|null  $regionCodes  null — все включённые регионы
     * @return int сколько заданий поставлено
     */
    public function plan(Carbon $until, int $days, ?array $regionCodes = null): int
    {
        $days = max(1, min($days, self::MAX_DAYS));

        $query = Region::query()->where('is_enabled', true)->orderBy('code');
        if ($regionCodes !== null) {
            $query->whereIn('code', $regionCodes);
        }
        $codes = $query->pluck('code')->all();

        $count = 0;
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $until->copy()->subDays($offset)->toDateString();
            foreach ($codes as $code) {
                $this->bus->dispatch(new ImportEisDay((string) $code, $day));
                $count++;
            }
        }

        return $count;
    }
}
