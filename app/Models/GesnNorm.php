<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Норма ГЭСН (ФСНБ-2022). Затраты труда хранятся в тысячных долях чел.-ч.
 *
 * @property string $code
 * @property string $kind
 * @property string $table_name
 * @property string $name
 * @property string $unit
 * @property list<array{code: string, milli: int}> $labor
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['code', 'kind', 'table_name', 'name', 'unit', 'labor'])]
class GesnNorm extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['labor' => 'array'];
    }
}
