<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'почта',
            self::WhatsApp => 'WhatsApp',
            self::Telegram => 'Telegram',
        };
    }
}
