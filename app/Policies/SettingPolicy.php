<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Настройки (в том числе токен бота) меняет только администратор.
 */
class SettingPolicy
{
    public function update(User $user): bool
    {
        return $user->is_admin;
    }
}
