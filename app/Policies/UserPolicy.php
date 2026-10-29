<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewUsers->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionName::UpdateUsers->value) && $user->isNot($model);
    }
}
