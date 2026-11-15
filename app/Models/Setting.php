<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Настройка из интерфейса. Значение хранится зашифрованным (APP_KEY).
 *
 * @property string $key
 * @property string $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }
}
