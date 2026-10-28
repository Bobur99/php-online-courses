<?php

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /** @return array{user: User, token: string} */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->role = 'student';
        $user->is_blocked = false;
        $user->save();

        UserRegistered::dispatch($user);

        return ['user' => $user, 'token' => $this->issueToken($user)];
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        return $user && Hash::check($password, $user->password) ? $user : null;
    }

    public function issueToken(User $user): string
    {
        return $user->createToken('api-token')->plainTextToken;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
