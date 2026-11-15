<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Estimate;
use App\Models\User;

/**
 * Быстрые сметы — внутренний расчёт, только администраторы.
 */
class EstimatePolicy
{
    public function manage(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Estimate $estimate): bool
    {
        return $user->is_admin;
    }
}
