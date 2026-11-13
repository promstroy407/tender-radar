<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcurementLaw: string
{
    case Fz44 = 'fz44';
    case Fz223 = 'fz223';

    public function label(): string
    {
        return match ($this) {
            self::Fz44 => '44-ФЗ',
            self::Fz223 => '223-ФЗ',
        };
    }
}
