<?php

declare(strict_types=1);

namespace App\Enums;

enum ContractKind: string
{
    /** Профильный тендер: электромонтаж или слаботочные системы целиком. */
    case Profile = 'profile';

    /** Генподряд: стройка или капремонт, где наши работы — часть объёма. */
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'Профильный',
            self::General => 'Генподряд',
        };
    }
}
