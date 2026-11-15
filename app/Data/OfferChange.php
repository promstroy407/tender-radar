<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\OfferStatus;
use Illuminate\Support\Carbon;

/**
 * Изменение КП с экрана «В работе», уже прошедшее валидацию.
 */
final readonly class OfferChange
{
    public function __construct(
        public OfferStatus $status,
        public ?Carbon $remindOn,
        public ?string $note,
    ) {}
}
