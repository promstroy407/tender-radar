<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * Суммы, напоминание и заметка из карточки сделки, уже прошедшие валидацию.
 */
final readonly class DealChange
{
    public function __construct(
        public ?int $offerAmountKopecks,
        public ?int $contractAmountKopecks,
        public ?int $paidKopecks,
        public ?Carbon $remindOn,
        public ?string $note,
    ) {}
}
