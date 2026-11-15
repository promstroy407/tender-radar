<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Eis\EisProbe;
use Illuminate\Console\Command;

class EisProbeCommand extends Command
{
    protected $signature = 'eis:probe {--region=12 : код региона} {--date= : дата ГГГГ-ММ-ДД, по умолчанию вчера} {--subsystem=RGK} {--type= : тип документа}';

    protected $description = 'ЕИС: проверить доступ, схему и пробную выгрузку контрактов (отчёт — на странице «Настройки → ЕИС»)';

    public function handle(EisProbe $probe): int
    {
        $date = $this->option('date');
        $type = $this->option('type');

        $report = $probe->run(
            (string) $this->option('region'),
            is_string($date) && $date !== '' ? $date : null,
            (string) $this->option('subsystem'),
            is_string($type) && $type !== '' ? $type : null,
        );

        if (($report['ok'] ?? false) !== true) {
            $this->error((string) ($report['error'] ?? 'Ошибка'));

            return self::FAILURE;
        }

        $this->line('Архивов: '.(string) ($report['archives'] ?? 0).'. Отчёт: «Настройки → ЕИС».');

        return self::SUCCESS;
    }
}
