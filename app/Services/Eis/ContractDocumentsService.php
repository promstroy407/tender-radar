<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\DocumentsToEstimateResult;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\User;
use App\Services\Estimates\EstimateService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Документация закупки по контракту: список файлов из извещения ЕИС и загрузка
 * этих файлов в смету по кнопке.
 */
class ContractDocumentsService
{
    /** Подсистема размещения извещений 44-ФЗ. */
    private const NOTICES = 'PRIZ';

    /** Больше — не тянем в смету: это чертежи-сканы, их всё равно не прочитать. */
    public const MAX_FILE_BYTES = 64 * 1024 * 1024;

    public function __construct(
        private readonly EisClient $client,
        private readonly EisArchiveReader $archives,
        private readonly NoticeDocumentsParser $parser,
        private readonly EstimateService $estimates,
        private readonly ConnectionInterface $db,
    ) {}

    /** Находит извещение и сохраняет список файлов документации. Возвращает их число. */
    public function fetch(Contract $contract): int
    {
        $documents = [];

        if ($contract->notification_number !== null) {
            foreach ($this->client->docsByReestrNumber(self::NOTICES, $contract->notification_number) as $url) {
                foreach ($this->archives->xmlFiles($this->client->downloadWhenReady($url)) as $xml) {
                    foreach ($this->parser->parse($xml) as $document) {
                        // В извещении и его изменениях один и тот же файл лежит под разными
                        // ссылками: оставляем один экземпляр (имя + размер), последний.
                        $documents[mb_strtolower($document->name).'|'.($document->sizeBytes ?? '')] = $document;
                    }
                }
            }
        }

        $this->db->transaction(function () use ($contract, $documents): void {
            $contract->documents()->delete();
            foreach ($documents as $document) {
                $contract->documents()->create([
                    'name' => $document->name,
                    'url' => $document->url,
                    'description' => $document->description,
                    'size_bytes' => $document->sizeBytes,
                ]);
            }
            $contract->forceFill(['documents_checked_at' => Carbon::now()])->save();
        });

        return count($documents);
    }

    /** Скачивает файлы документации и добавляет их в смету тендера (разбор — в фоне). */
    public function loadIntoEstimate(Contract $contract, User $user): DocumentsToEstimateResult
    {
        $estimate = $this->estimates->forContract($contract, $user);
        $loaded = 0;
        $skipped = [];
        $present = array_values(array_map(strval(...), $estimate->files()->pluck('original_name')->all()));

        /** @var ContractDocument $document */
        foreach ($contract->documents()->get() as $document) {
            // Повторное нажатие не добавляет те же файлы (архив лежит как «архив → файл»).
            if ($this->alreadyLoaded($document->name, $present)) {
                continue;
            }

            if ($document->size_bytes !== null && $document->size_bytes > self::MAX_FILE_BYTES) {
                $skipped[] = $document->name.' — больше 64 МБ';

                continue;
            }

            try {
                $bytes = $this->client->downloadArchive($document->url);
            } catch (EisException $e) {
                $skipped[] = $document->name.' — '.$e->getMessage();

                continue;
            }

            $path = (string) tempnam(sys_get_temp_dir(), 'doc');
            file_put_contents($path, $bytes);
            try {
                $this->estimates->addUploads($estimate, [new UploadedFile($path, $document->name, null, null, true)]);
                $loaded++;
            } finally {
                @unlink($path);
            }
        }

        return new DocumentsToEstimateResult($loaded, $skipped);
    }

    /**
     * @param  list<string>  $present  имена файлов, уже загруженных в смету
     */
    private function alreadyLoaded(string $name, array $present): bool
    {
        foreach ($present as $existing) {
            if ($existing === $name || str_starts_with($existing, $name.' → ')) {
                return true;
            }
        }

        return false;
    }
}
