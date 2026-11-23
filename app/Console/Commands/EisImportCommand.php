<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Eis\EisImportPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Сбор контрактов из ЕИС. По расписанию — каждое утро за последние дни
 * (ЕИС публикует контракты с задержкой, повтор дня ничего не дублирует).
 */
class EisImportCommand extends Command
{
    protected $signature = 'eis:import
        {--days=3 : за сколько последних дней}
        {--until= : последний день, ГГГГ-ММ-ДД (по умолчанию вчера)}
        {--region=* : коды регионов (по умолчанию все включённые)}';

    protected $description = 'ЕИС: поставить в очередь сбор контрактов по регионам и дням';

    public function handle(EisImportPlanner $planner): int
    {
        $until = $this->option('until');
        if (is_string($until) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) !== 1) {
            $this->error('--until — дата в виде ГГГГ-ММ-ДД.');

            return self::FAILURE;
        }

        $regions = array_values(array_filter((array) $this->option('region'), 'is_string'));

        $count = $planner->plan(
            is_string($until) ? Carbon::parse($until) : Carbon::yesterday(),
            (int) $this->option('days'),
            $regions !== [] ? $regions : null,
        );

        $this->line('Поставлено заданий: '.$count.'.');

        return self::SUCCESS;
    }
}
