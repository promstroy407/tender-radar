<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ContractFilter;

/**
 * Параметры поиска контрактов, уже прошедшие валидацию.
 */
final readonly class ContractSearch
{
    /**
     * @param  list<string>  $regionCodes  пустой список — все включённые регионы
     */
    public function __construct(
        public ContractFilter $filter = ContractFilter::All,
        public array $regionCodes = [],
        public ?int $minAmountKopecks = null,
        public int $perPage = 20,
    ) {}
}
