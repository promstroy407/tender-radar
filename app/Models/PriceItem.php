<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceSection;
use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property list<array{title: string, url: string, price_kopecks: int, note?: string}>|null $market_sources
 * @property Carbon|null $market_checked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['section', 'name', 'unit', 'work_price_kopecks', 'gesn_code', 'keywords', 'is_active', 'market_sources', 'market_checked_at'])]
class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory;

    /**
     * Строки смет, сопоставленные с позицией.
     *
     * @return HasMany<EstimateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class);
    }

    /**
     * Рыночная цена — максимальная из источников (решение заказчика 26.11.2026: «чтобы не
     * работать в минус»); null — источников нет.
     */
    public function marketPriceKopecks(): ?int
    {
        $prices = array_map(fn (array $source): int => (int) $source['price_kopecks'], $this->market_sources ?? []);

        return $prices === [] ? null : max($prices);
    }

    /** Позиция демонтажа: по ней считаются только строки сметы с демонтажем. */
    public function isDemolition(): bool
    {
        return str_starts_with(mb_strtolower($this->name), 'демонтаж');
    }

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
            'market_sources' => 'array',
            'market_checked_at' => 'date',
        ];
    }
}
