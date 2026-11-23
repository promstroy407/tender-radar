<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contract;
use App\Services\Eis\ContractDocumentsService;
use App\Services\Eis\EisException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Psr\Log\LoggerInterface;

/**
 * Поиск документации закупки по контракту в ЕИС — в фоне, после сбора контрактов.
 */
class FetchContractDocuments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Отдельная очередь: воркер берёт её раньше общей (сбор контрактов идёт часами). */
    public const QUEUE = 'documents';

    public int $timeout = 300;

    public int $tries = 2;

    public int $backoff = 600;

    public function __construct(
        public readonly int $contractId,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return (string) $this->contractId;
    }

    public function handle(ContractDocumentsService $documents, LoggerInterface $log): void
    {
        $contract = Contract::query()->find($this->contractId);

        if ($contract === null) {
            return;
        }

        try {
            $documents->fetch($contract);
        } catch (EisException $e) {
            $log->warning('Документация закупки не получена: '.$e->getMessage(), ['contract_id' => $contract->id]);
        }
    }
}
