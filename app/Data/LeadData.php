<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * Заявка с сайта es12.ru, уже прошедшая валидацию.
 */
final readonly class LeadData
{
    public function __construct(
        public string $externalId,
        public Carbon $receivedAt,
        public string $name,
        public string $phone,
        public ?string $email,
        public ?string $organization,
        public ?string $facility,
        public ?string $service,
        public ?string $origin,
        public ?string $message,
        public ?string $estimate,
        public ?string $sourceUrl,
    ) {}
}
