<?php

declare(strict_types=1);

namespace App\Enums;

/** Быстрые фильтры на главном экране. */
enum ContractFilter: string
{
    case All = 'all';
    case Hot = 'hot';
    case Profile = 'profile';
    case General = 'general';
}
