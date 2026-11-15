<?php

declare(strict_types=1);

namespace App\Enums;

enum EstimateFileStatus: string
{
    case Pending = 'pending';
    case Parsed = 'parsed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'читается…',
            self::Parsed => 'прочитан',
            self::Failed => 'не прочитан',
        };
    }
}
