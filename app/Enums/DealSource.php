<?php

declare(strict_types=1);

namespace App\Enums;

/** Откуда пришла сделка. */
enum DealSource: string
{
    case Tender = 'tender';
    case Site = 'site';
    case Call = 'call';
    case Recommendation = 'recommendation';
    case Repeat = 'repeat';

    public function label(): string
    {
        return match ($this) {
            self::Tender => 'Тендер',
            self::Site => 'Сайт',
            self::Call => 'Звонок',
            self::Recommendation => 'Рекомендация',
            self::Repeat => 'Повторный клиент',
        };
    }
}
