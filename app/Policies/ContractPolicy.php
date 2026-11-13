<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

/**
 * Тендеры и контакты победителей видят только администраторы.
 */
class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->is_admin;
    }
}
