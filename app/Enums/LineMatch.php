<?php

declare(strict_types=1);

namespace App\Enums;

/** Как позиция сметы связана с нашим прайсом. */
enum LineMatch: string
{
    case Auto = 'auto';
    case Manual = 'manual';
    case Skip = 'skip';
}
