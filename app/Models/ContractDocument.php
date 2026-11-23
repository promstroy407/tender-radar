<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Файл документации закупки в ЕИС: имя и ссылка для скачивания.
 *
 * @property int $id
 * @property int $contract_id
 * @property string $name
 * @property string $url
 * @property string|null $description
 * @property int|null $size_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contract $contract
 */
#[Fillable(['contract_id', 'name', 'url', 'description', 'size_bytes'])]
class ContractDocument extends Model
{
    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** Расширение файла в нижнем регистре: «xlsx», «pdf», «zip». */
    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}
