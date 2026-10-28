<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ProfileUpdateRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private UserService $userService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return response()->json($this->authService->register($request->validated()), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->authenticate(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if (! $user) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->is_blocked) {
            return response()->json([
                'message' => 'User is blocked.',
            ], 403);
        }

        return response()->json([
            'user' => $user,
            'token' => $this->authService->issueToken($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

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
        return response()->json($this->userService->updateProfile($request->user(), $request->validated()));
    }

    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {
        if (! $this->userService->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        )) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
