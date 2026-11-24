<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstimateMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Материал или оборудование из сметы заказчика: количество и стоимость по смете.
 *
 * @property int $id
 * @property int $estimate_id
 * @property int $estimate_file_id
 * @property int $position
 * @property string $code
 * @property string $name
 * @property string|null $unit
 * @property int $quantity_milli
 * @property int $total_kopecks
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EstimateFile $file
 */
#[Fillable(['estimate_id', 'estimate_file_id', 'position', 'code', 'name', 'unit', 'quantity_milli', 'total_kopecks'])]
class EstimateMaterial extends Model
{
    /** @use HasFactory<EstimateMaterialFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<EstimateFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(EstimateFile::class, 'estimate_file_id');
    }

    /**
     * Цена за единицу по смете: сумма ÷ количество, до копейки («половина вверх»).
     * Вычисляется, не хранится. null — количество нулевое.
     */
    public function unitPriceKopecks(): ?int
    {
        if ($this->quantity_milli <= 0) {
            return null;
        }

        return intdiv($this->total_kopecks * 1000 * 2 + $this->quantity_milli, $this->quantity_milli * 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity_milli' => 'integer',
            'total_kopecks' => 'integer',
        ];
    }
}
