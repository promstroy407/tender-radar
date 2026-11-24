<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\MaterialGroup;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateMaterial;

/** Собирает раздел «Лоток и комплектующие» из материалов смет заказчика. */
class TrayReportBuilder
{
    public function __construct(
        private readonly TrayClassifier $classifier,
    ) {}

    public function build(Estimate $estimate): TrayReport
    {
        /** @var list<EstimateMaterial> $materials */
        $materials = $estimate->materials()->orderBy('estimate_file_id')->orderBy('position')->get()->all();

        $withTrays = false;
        foreach ($materials as $material) {
            if ($this->classifier->isTray($material->name)) {
                $withTrays = true;

                break;
            }
        }

        $groups = [];
        $materialsKopecks = 0;
        foreach ($materials as $material) {
            $materialsKopecks += $material->total_kopecks;
            $group = $this->classifier->classify($material->name, $withTrays);
            if ($group === null) {
                continue;
            }
            $groups[$group->value] ??= ['group' => $group, 'rows' => [], 'kopecks' => 0];
            $groups[$group->value]['rows'][] = $material;
            $groups[$group->value]['kopecks'] += $material->total_kopecks;
        }

        // Порядок групп — как в перечне: лотки, комплектующие, стяжки.
        $ordered = [];
        foreach (MaterialGroup::cases() as $case) {
            if (isset($groups[$case->value])) {
                $ordered[] = $groups[$case->value];
            }
        }

        $normative = $normativeFiles = $toReread = 0;
        $withMaterials = array_flip(array_map(fn (EstimateMaterial $m): int => $m->estimate_file_id, $materials));
        foreach ($estimate->files as $file) {
            /** @var EstimateFile $file */
            $totals = $file->normativeTotals();
            if ($totals === null) {
                continue;
            }
            $normativeFiles++;
            $normative += $totals->materialsKopecks + $totals->equipmentKopecks;
            // Сканы распознаются не целиком (итоговые страницы) — перечитывание материалов не даст.
            if (! isset($withMaterials[$file->id]) && $file->extension() !== 'pdf') {
                $toReread++;
            }
        }

        return new TrayReport($ordered, count($materials), $materialsKopecks, $normative, $normativeFiles, $toReread);
    }
}
