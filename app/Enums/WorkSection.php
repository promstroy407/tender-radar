<?php

declare(strict_types=1);

namespace App\Enums;

/** Разделы работ, которые мы ищем в документации закупки. */
enum WorkSection: string
{
    case Eom = 'eom';
    case Ss = 'ss';
    case Aps = 'aps';
    case Soue = 'soue';
    case Skud = 'skud';
    case Video = 'video';
    case Sks = 'sks';

    public function label(): string
    {
        return match ($this) {
            self::Eom => 'ЭОМ — электроснабжение',
            self::Ss => 'СС — сети связи',
            self::Aps => 'АПС — пожарная сигнализация',
            self::Soue => 'СОУЭ — оповещение',
            self::Skud => 'СКУД',
            self::Video => 'Видеонаблюдение',
            self::Sks => 'СКС',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Eom => 'ЭОМ',
            self::Ss => 'СС',
            self::Aps => 'АПС',
            self::Soue => 'СОУЭ',
            self::Skud => 'СКУД',
            self::Video => 'Видеонаблюдение',
            self::Sks => 'СКС',
        };
    }
}
