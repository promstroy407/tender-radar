<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Компания-победитель закупки.
 *
 * @property int $id
 * @property string $inn
 * @property string|null $kpp
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $director_name
 * @property string|null $city
 */
#[Fillable(['inn', 'kpp', 'name', 'email', 'phone', 'website', 'director_name', 'city'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
