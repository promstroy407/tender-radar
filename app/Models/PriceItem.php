<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceSection;
use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Позиция нашего прайса работ.
 *
 * @property int $id
 * @property PriceSection $section
 * @property string $name
 * @property string $unit
 * @property int|null $work_price_kopecks
 * @property string|null $gesn_code
 * @property string $keywords
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['section', 'name', 'unit', 'work_price_kopecks', 'gesn_code', 'keywords', 'is_active'])]
class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory;

    /**
     * Ключевые фразы: каждая — набор слов, которые все должны встретиться в позиции сметы.
     *
     * @return list<string>
     */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->keywords)), fn (string $k): bool => $k !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'section' => PriceSection::class,
            'work_price_kopecks' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
