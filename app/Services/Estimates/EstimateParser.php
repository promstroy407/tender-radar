<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Разбор одного файла: строки таблицы → позиции → сопоставление с прайсом.
 * Повторный разбор заменяет позиции файла (ручные сопоставления сбрасываются).
 */
class EstimateParser
{
    public function __construct(
        private readonly DocumentReader $reader,
        private readonly TableExtractor $extractor,
        private readonly PriceMatcher $matcher,
        private readonly FilesystemFactory $storage,
        private readonly ConnectionInterface $db,
    ) {}

    public function parse(EstimateFile $file): void
    {
        try {
            $tables = $this->reader->read($this->storage->disk('local')->path($file->path), $file->extension());

            $lines = [];
            foreach ($tables as $rows) {
                array_push($lines, ...$this->extractor->extract($rows));
            }

            if ($lines === []) {
                throw new EstimateReadException('Позиций не нашли: нужна таблица с наименованием, единицей и количеством.');
            }

            $this->store($file, $lines);
        } catch (EstimateReadException $e) {
            $this->fail($file, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->fail($file, 'Ошибка при чтении файла: '.$e->getMessage());
        }
    }

    /**
     * @param  list<ExtractedLine>  $lines
     */
    private function store(EstimateFile $file, array $lines): void
    {
        $items = PriceItem::query()->where('is_active', true)->get();
        $now = now();

        $this->db->transaction(function () use ($file, $lines, $items, $now): void {
            $file->lines()->delete();

            $rows = [];
            foreach ($lines as $position => $line) {
                $item = $this->matcher->match($line->name, $line->unit, $items);
                $rows[] = [
                    'estimate_id' => $file->estimate_id,
                    'estimate_file_id' => $file->id,
                    'position' => $position + 1,
                    'name' => $line->name,
                    'unit' => $line->unit === null ? null : mb_substr($line->unit, 0, 32),
                    'quantity_milli' => $line->quantityMilli,
                    'material_kopecks' => $line->materialKopecks,
                    'price_item_id' => $item?->id,
                    'match' => LineMatch::Auto->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                EstimateLine::query()->insert($chunk);
            }

            $file->forceFill(['status' => EstimateFileStatus::Parsed, 'error' => null])->save();
        });
    }

    private function fail(EstimateFile $file, string $message): void
    {
        $file->lines()->delete();
        $file->forceFill(['status' => EstimateFileStatus::Failed, 'error' => mb_substr($message, 0, 1000)])->save();
    }
}
