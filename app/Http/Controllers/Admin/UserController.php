<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockUserRequest;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json([
            'data' => $this->userService->all(),
        ]);
    }

    public function updateRole(
        ChangeUserRoleRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('update', $user);

        return response()->json($this->userService->changeRole($user, $request->validated('role')));
    }

    public function block(
        BlockUserRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('update', $user);

        return response()->json($this->userService->setBlocked($user, $request->boolean('is_blocked')));
    }
}
