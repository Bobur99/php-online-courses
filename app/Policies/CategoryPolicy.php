<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CreateCategories->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(PermissionName::UpdateCategories->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(PermissionName::DeleteCategories->value);
    }
}
