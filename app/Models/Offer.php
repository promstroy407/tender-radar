<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferChannel;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Отправка КП победителю по контракту: канал, когда, кто.
 * Статус, напоминание и заметка — у сделки (deals).
 *
 * @property int $id
 * @property int $deal_id
 * @property int $contract_id
 * @property OfferChannel $channel
 * @property Carbon $sent_at
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Deal $deal
 * @property-read Contract $contract
 */
#[Fillable(['deal_id', 'contract_id', 'channel', 'sent_at', 'user_id'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'channel' => OfferChannel::class,
            'sent_at' => 'datetime',
        ];
    }
}
