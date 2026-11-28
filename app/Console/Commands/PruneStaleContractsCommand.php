<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Ежедневно убирает из ленты устаревшие контракты, по которым ничего не делали. */
class PruneStaleContractsCommand extends Command
{
    protected $signature = 'contracts:prune-stale';

    protected $description = 'Удалить контракты старше 30 дней без сделки, КП и просчёта';

    public function handle(ContractService $contracts): int
    {
        $deleted = $contracts->pruneStale(Carbon::now());
        $this->info('Удалено устаревших контрактов: '.$deleted);

        return self::SUCCESS;
    }
}
