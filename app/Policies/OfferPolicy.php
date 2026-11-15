<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

/**
 * Отправка КП и статусы — только администраторы, как и сами тендеры.
 */
class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->is_admin;
    }
}
