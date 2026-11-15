<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriceItem;
use App\Models\User;

/** Прайс работ правят только администраторы. */
class PriceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, PriceItem $item): bool
    {
        return $user->is_admin;
    }
}
