<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferChannel;
use App\Enums\OfferStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * КП победителю по контракту. Победитель и сумма берутся из контракта, здесь не дублируются.
 *
 * @property int $id
 * @property int $contract_id
 * @property OfferStatus $status
 * @property OfferChannel|null $channel
 * @property Carbon|null $sent_at
 * @property Carbon|null $remind_on
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contract $contract
 */
#[Fillable(['contract_id', 'status', 'channel', 'sent_at', 'remind_on', 'note', 'user_id'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

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
            'status' => OfferStatus::class,
            'channel' => OfferChannel::class,
            'sent_at' => 'datetime',
            'remind_on' => 'date',
        ];
    }
}
