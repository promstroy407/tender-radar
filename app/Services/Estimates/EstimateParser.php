<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\EstimateMaterial;
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
        private readonly NormativeTotalsExtractor $normative,
        private readonly ResourceExtractor $resources,
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

            $totals = $this->normative->extract($tables);

            if ($lines === [] && $totals === null) {
                throw new EstimateReadException('Позиций не нашли: нужна таблица с наименованием, единицей и количеством.');
            }

            // Нормативная смета заказчика: работы — строки ГЭСН/ФЕР/ТЕР (материалы и итоги
            // разделов в позиции не попадают), материалы и оборудование — с их стоимостью.
            $materials = [];
            if ($totals !== null) {
                $lines = $this->resources->works($tables);
                $materials = $this->resources->materials($tables);
            }

            $this->store($file, $lines, $totals, $materials);
        } catch (EstimateReadException $e) {
            $this->fail($file, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->fail($file, 'Ошибка при чтении файла: '.$e->getMessage());
        }
    }

    /**
     * @param  list<ExtractedLine>  $lines
     * @param  list<ExtractedMaterial>  $materials
     */
    private function store(EstimateFile $file, array $lines, ?NormativeTotals $totals, array $materials): void
    {
        $items = PriceItem::query()->where('is_active', true)->get();
        $now = now();

        $this->db->transaction(function () use ($file, $lines, $items, $now, $totals, $materials): void {
            $file->lines()->delete();
            $file->materials()->delete();

            $resourceRows = [];
            foreach ($materials as $position => $material) {
                $resourceRows[] = [
                    'estimate_id' => $file->estimate_id,
                    'estimate_file_id' => $file->id,
                    'position' => $position + 1,
                    'code' => $material->code,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'quantity_milli' => $material->quantityMilli,
                    'total_kopecks' => $material->totalKopecks,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($resourceRows, 500) as $chunk) {
                EstimateMaterial::query()->insert($chunk);
            }

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

            $file->forceFill([
                'status' => EstimateFileStatus::Parsed,
                'error' => null,
                'norm_total_kopecks' => $totals?->totalKopecks,
                'norm_materials_kopecks' => $totals?->materialsKopecks,
                'norm_equipment_kopecks' => $totals?->equipmentKopecks,
            ])->save();
        });
    }

    private function fail(EstimateFile $file, string $message): void
    {
        $file->lines()->delete();
        $file->materials()->delete();
        $file->forceFill([
            'status' => EstimateFileStatus::Failed,
            'error' => mb_substr($message, 0, 1000),
            'norm_total_kopecks' => null,
            'norm_materials_kopecks' => null,
            'norm_equipment_kopecks' => null,
        ])->save();
    }
}
