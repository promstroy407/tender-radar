<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstimateFileStatus;
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
 * @property-read Estimate $estimate
 */
#[Fillable(['estimate_id', 'original_name', 'path', 'size_bytes', 'status', 'error'])]
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
        ];
    }
}
