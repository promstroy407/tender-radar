<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Сделка — одна запись воронки: тендер (КП победителю) или заявка клиента.
 *
 * @property int $id
 * @property DealSource $source
 * @property DealStatus $status
 * @property DealStatus $reached_status
 * @property int|null $contract_id
 * @property int|null $company_id
 * @property string|null $external_id
 * @property string|null $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $organization
 * @property string|null $facility
 * @property string|null $service
 * @property string|null $origin
 * @property string|null $message
 * @property string|null $estimate
 * @property string|null $source_url
 * @property Carbon|null $received_at
 * @property int|null $offer_amount_kopecks
 * @property int|null $contract_amount_kopecks
 * @property int|null $paid_kopecks
 * @property Carbon|null $remind_on
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contract|null $contract
 * @property-read Company|null $company
 * @property-read Offer|null $offer
 */
#[Fillable([
    'source', 'status', 'reached_status', 'contract_id', 'company_id', 'external_id',
    'name', 'phone', 'email', 'organization', 'facility', 'service', 'origin', 'message', 'estimate', 'source_url',
    'received_at', 'offer_amount_kopecks', 'contract_amount_kopecks', 'paid_kopecks', 'remind_on', 'note', 'user_id',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Отправленное КП (для тендерных сделок).
     *
     * @return HasOne<Offer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    /** Как называть клиента в списке: организация, имя или победитель тендера. */
    public function title(): string
    {
        return $this->organization ?? $this->company?->name ?? $this->name ?? 'Сделка №'.$this->id;
    }

    /** Остаток к оплате по договору; null — сумма договора не указана. */
    public function remainderKopecks(): ?int
    {
        return $this->contract_amount_kopecks === null
            ? null
            : $this->contract_amount_kopecks - ($this->paid_kopecks ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => DealSource::class,
            'status' => DealStatus::class,
            'reached_status' => DealStatus::class,
            'received_at' => 'datetime',
            'remind_on' => 'date',
            'offer_amount_kopecks' => 'integer',
            'contract_amount_kopecks' => 'integer',
            'paid_kopecks' => 'integer',
        ];
    }
}
