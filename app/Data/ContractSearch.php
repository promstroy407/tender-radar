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
     * @param  bool  $onlyNew  только контракты, по которым КП ещё не отправлено и не отклонено
     */
    public function __construct(
        public ContractFilter $filter = ContractFilter::All,
        public array $regionCodes = [],
        public ?int $minAmountKopecks = null,
        public int $perPage = 20,
        public bool $onlyNew = false,
    ) {}

    public function withFilter(ContractFilter $filter): self
    {
        return new self($filter, $this->regionCodes, $this->minAmountKopecks, $this->perPage, $this->onlyNew);
    }
}
