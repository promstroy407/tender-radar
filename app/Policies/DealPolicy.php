<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;

/**
 * Сделки и воронка — только администраторы, как тендеры и КП.
 */
class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Deal $deal): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Deal $deal): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $user->is_admin;
    }
}
