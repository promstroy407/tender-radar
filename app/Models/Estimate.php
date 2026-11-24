<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Быстрая смета для себя: по тендеру (есть контракт) или расчёт в «Калькуляторе»
 * (сторонняя смета: своё название и регион).
 *
 * @property int $id
 * @property int|null $contract_id
 * @property string|null $name
 * @property string|null $region_code
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contract|null $contract
 * @property-read Region|null $region
 * @property-read Collection<int, EstimateFile> $files
 * @property-read Collection<int, EstimateLine> $lines
 * @property-read Collection<int, EstimateMaterial> $materials
 */
#[Fillable(['contract_id', 'name', 'region_code', 'user_id'])]
class Estimate extends Model
{
    /** @use HasFactory<EstimateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Регион расчёта из «Калькулятора»; у сметы тендера — пусто (регион у контракта).
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    /** Расчёт из «Калькулятора», не из тендера. */
    public function isCalculation(): bool
    {
        return $this->contract_id === null;
    }

    /** Регион, по ставкам которого считаются работы. */
    public function regionCode(): string
    {
        return $this->contract->region_code ?? (string) $this->region_code;
    }

    /** Название для заголовка: предмет контракта или имя расчёта. */
    public function title(): string
    {
        return $this->contract->subject ?? (string) $this->name;
    }

    /**
     * Материалы и оборудование из смет заказчика.
     *
     * @return HasMany<EstimateMaterial, $this>
     */
    public function materials(): HasMany
    {
        return $this->hasMany(EstimateMaterial::class);
    }

    /**
     * @return HasMany<EstimateFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(EstimateFile::class);
    }

    /**
     * @return HasMany<EstimateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class);
    }
}
