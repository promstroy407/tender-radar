<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Отправка КП — только администраторы, как и сами тендеры.
 */
class OfferPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
