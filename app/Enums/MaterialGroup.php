<?php

declare(strict_types=1);

namespace App\Enums;

/** Группы раздела «Лоток и комплектующие». Определяются по наименованию, не хранятся. */
enum MaterialGroup: string
{
    case TrayPerforated = 'tray_perforated';
    case TraySolid = 'tray_solid';
    case TrayOther = 'tray_other';
    case Fittings = 'fittings';
    case Ties = 'ties';

    public function label(): string
    {
        return match ($this) {
            self::TrayPerforated => 'Лоток перфорированный',
            self::TraySolid => 'Лоток неперфорированный',
            self::TrayOther => 'Лоток лестничный, проволочный и прочий',
            self::Fittings => 'Комплектующие лотка',
            self::Ties => 'Стяжки металлические',
        };
    }
}
