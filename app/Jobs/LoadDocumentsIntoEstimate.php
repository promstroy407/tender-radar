<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contract;
use App\Models\User;
use App\Services\Eis\ContractDocumentsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Psr\Log\LoggerInterface;

/**
 * Скачивание документации закупки в смету — в фоне: файлов бывает десятки мегабайт.
 */
class LoadDocumentsIntoEstimate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Отдельная очередь: воркер берёт её раньше общей (сбор контрактов идёт часами). */
    public const QUEUE = 'documents';

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public readonly int $contractId,
        public readonly int $userId,
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
        $user = User::query()->find($this->userId);

        if ($contract === null || $user === null) {
            return;
        }

        $result = $documents->loadIntoEstimate($contract, $user);

        if ($result->skipped !== []) {
            $log->warning('Часть документации не загружена в смету', ['contract_id' => $contract->id, 'skipped' => $result->skipped]);
        }
    }
}
