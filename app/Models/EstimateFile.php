<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstimateFileStatus;
use App\Services\Estimates\NormativeTotals;
use Database\Factories\EstimateFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Файл документации тендера, загруженный для расчёта.
 *
 * @property int $id
 * @property int $estimate_id
 * @property string $original_name
 * @property string $path
 * @property int $size_bytes
 * @property EstimateFileStatus $status
 * @property string|null $error
 * @property int|null $norm_total_kopecks
 * @property int|null $norm_materials_kopecks
 * @property int|null $norm_equipment_kopecks
 * @property-read Estimate $estimate
 */
#[Fillable(['estimate_id', 'original_name', 'path', 'size_bytes', 'status', 'error', 'norm_total_kopecks', 'norm_materials_kopecks', 'norm_equipment_kopecks'])]
class EstimateFile extends Model
{
    /** @use HasFactory<EstimateFileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /**
     * @return HasMany<EstimateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class);
    }

    /** Итоги, если файл — нормативная смета заказчика; иначе null. */
    public function normativeTotals(): ?NormativeTotals
    {
        return $this->norm_total_kopecks === null ? null : new NormativeTotals(
            $this->norm_total_kopecks,
            $this->norm_materials_kopecks ?? 0,
            $this->norm_equipment_kopecks ?? 0,
        );
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => EstimateFileStatus::class,
            'size_bytes' => 'integer',
            'norm_total_kopecks' => 'integer',
            'norm_materials_kopecks' => 'integer',
            'norm_equipment_kopecks' => 'integer',
        ];
    }
}
