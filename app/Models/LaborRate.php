<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Сметная цена 1 чел.-ч рабочего (или машиниста) в регионе, копейки.
 *
 * @property int $id
 * @property string $region_code
 * @property string $code
 * @property int $rate_kopecks
 * @property string $period
 */
#[Fillable(['region_code', 'code', 'rate_kopecks', 'period'])]
class LaborRate extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['rate_kopecks' => 'integer'];
    }
}
