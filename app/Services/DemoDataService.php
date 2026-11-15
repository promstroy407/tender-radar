<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use Database\Seeders\DemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;

/**
 * Демо-контракты для показа экранов, пока нет данных из ЕИС.
 * Все помечены «[Демо]» и удаляются одной кнопкой.
 */
class DemoDataService
{
    private const NUMBER_PREFIX = 'DEMO-';

    private const INN_PREFIX = '000000000';

    public function __construct(
        private readonly Kernel $artisan,
        private readonly ConnectionInterface $db,
    ) {}

    public function exists(): bool
    {
        return Contract::query()->where('registry_number', 'like', self::NUMBER_PREFIX.'%')->exists();
    }

    public function load(): void
    {
        $this->artisan->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
    }

    public function remove(): void
    {
        $this->db->transaction(function (): void {
            // КП по демо-контрактам удаляются каскадом.
            Contract::query()->where('registry_number', 'like', self::NUMBER_PREFIX.'%')->delete();
            Company::query()
                ->where('inn', 'like', self::INN_PREFIX.'%')
                ->whereDoesntHave('contracts')
                ->delete();
        });
    }
}
