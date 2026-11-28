<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ContractFilter;
use Carbon\CarbonImmutable;

/**
 * Параметры поиска контрактов, уже прошедшие валидацию.
 */
final readonly class ContractSearch
{
    /**
     * @param  list<string>  $regionCodes  пустой список — все включённые регионы
     * @param  bool  $onlyNew  только контракты, по которым КП ещё не отправлено и не отклонено
     * @param  int|null  $viewerId  для кого лента: его просмотренные — ниже и с отметкой
     * @param  bool  $onlyUnseen  только ещё не открытые этим пользователем
     * @param  bool  $onlyWithDocuments  только тендеры, где есть проект, смета или ведомость (они загружены в просчёт)
     * @param  CarbonImmutable|null  $loadedFrom  загружены в программу не раньше этого дня (включительно)
     * @param  CarbonImmutable|null  $loadedTo  загружены не позже этого дня (включительно)
     */
    public function __construct(
        public ContractFilter $filter = ContractFilter::All,
        public array $regionCodes = [],
        public ?int $minAmountKopecks = null,
        public int $perPage = 20,
        public bool $onlyNew = false,
        public ?int $viewerId = null,
        public bool $onlyUnseen = false,
        public bool $onlyWithDocuments = false,
        public ?CarbonImmutable $loadedFrom = null,
        public ?CarbonImmutable $loadedTo = null,
    ) {}

    public function withFilter(ContractFilter $filter): self
    {
        return new self($filter, $this->regionCodes, $this->minAmountKopecks, $this->perPage, $this->onlyNew, $this->viewerId, $this->onlyUnseen, $this->onlyWithDocuments, $this->loadedFrom, $this->loadedTo);
    }
}
