<?php

declare(strict_types=1);

namespace App\Enums;

/** Что за файл в документации закупки: для расчёта нужны проект, смета и ведомость работ. */
enum DocumentKind: string
{
    case Estimate = 'estimate';
    case WorksList = 'works_list';
    case Project = 'project';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Estimate => 'Смета',
            self::WorksList => 'Ведомость работ',
            self::Project => 'Проект',
            self::Other => 'Прочее',
        };
    }

    /** Нужен для просчёта работ. */
    public function isUseful(): bool
    {
        return $this !== self::Other;
    }
}
