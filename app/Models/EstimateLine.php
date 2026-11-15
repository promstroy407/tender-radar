<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LineMatch;
use Database\Factories\EstimateLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Позиция из сметы или ведомости заказчика.
 *
 * @property int $id
 * @property int $estimate_id
 * @property int $estimate_file_id
 * @property int $position
 * @property string $name
 * @property string|null $unit
 * @property int $quantity_milli
 * @property int|null $material_kopecks
 * @property int|null $price_item_id
 * @property LineMatch $match
 * @property-read PriceItem|null $priceItem
 * @property-read EstimateFile $file
 */
#[Fillable(['estimate_id', 'estimate_file_id', 'position', 'name', 'unit', 'quantity_milli', 'material_kopecks', 'price_item_id', 'match'])]
class EstimateLine extends Model
{
    /** @use HasFactory<EstimateLineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PriceItem, $this>
     */
    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }

    /**
     * @return BelongsTo<EstimateFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(EstimateFile::class, 'estimate_file_id');
    }

    /**
     * Наша стоимость работы по строке: количество × цена прайса, с округлением до копейки.
     * Вычисляется, не хранится. null — позиция не сопоставлена или у неё нет цены.
     */
    public function workKopecks(): ?int
    {
        if ($this->match === LineMatch::Skip) {
            return null;
        }

        $price = $this->priceItem?->work_price_kopecks;

        // Количество — в тысячных долях: делим на 1000 с округлением «половина вверх».
        return $price === null ? null : intdiv($this->quantity_milli * $price + 500, 1000);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'match' => LineMatch::class,
            'quantity_milli' => 'integer',
            'material_kopecks' => 'integer',
            'position' => 'integer',
        ];
    }
}
