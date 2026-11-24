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

/**
 * Быстрая смета по тендеру — для себя.
 *
 * @property int $id
 * @property int $contract_id
 * @property int|null $user_id
 * @property-read Contract $contract
 * @property-read Collection<int, EstimateFile> $files
 * @property-read Collection<int, EstimateLine> $lines
 * @property-read Collection<int, EstimateMaterial> $materials
 */
#[Fillable(['contract_id', 'user_id'])]
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
