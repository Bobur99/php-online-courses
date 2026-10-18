<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        $user->role = 'student';
        $user->is_blocked = false;
        $user->save();

        UserRegistered::dispatch($user);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where(
            'email',
            $request->string('email')->toString()
        )->first();

        if (! $user || ! Hash::check(
            $request->string('password')->toString(),
            $user->password
        )) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->is_blocked) {
            return response()->json([
                'message' => 'User is blocked.',
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateProfile(
        ProfileUpdateRequest $request
    ): JsonResponse {
        $user = $request->user();

        $user->fill($request->validated());
        $user->save();

        return response()->json($user);
    }

    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {
        $user = $request->user();

        if (! Hash::check(
            $request->string('current_password')->toString(),
            $user->password
        )) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = $request->string('password')->toString();
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
