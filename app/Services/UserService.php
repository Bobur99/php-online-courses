<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /** @return Collection<int, User> */
    public function all(): Collection
    {
        return User::query()->get();
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $password): bool
    {
        if (! Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->password = $password;
        $user->save();

        return true;
    }

    public function changeRole(User $user, string $role): User
    {
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function setBlocked(User $user, bool $isBlocked): User
    {
        $user->is_blocked = $isBlocked;
        $user->save();

        return $user;
    }
}
