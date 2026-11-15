<?php

declare(strict_types=1);

namespace App\Enums;

/** Разделы прайса — как на сайте es12.ru/ceny. */
enum PriceSection: string
{
    case Electric = 'electric';
    case Lowcurrent = 'lowcurrent';
    case Fire = 'fire';
    case Video = 'video';
    case Access = 'access';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Electric => 'Электромонтаж',
            self::Lowcurrent => 'Слаботочные системы и СКС',
            self::Fire => 'Пожарная сигнализация и СОУЭ',
            self::Video => 'Видеонаблюдение',
            self::Access => 'СКУД',
            self::Security => 'Охранная сигнализация',
        };
    }
}
