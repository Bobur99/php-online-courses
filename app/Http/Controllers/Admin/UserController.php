<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockUserRequest;
use App\Http\Requests\Admin\ChangeUserRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json([
            'data' => User::query()->get(),
        ]);
    }

    public function updateRole(
        ChangeUserRoleRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('update', $user);

        $user->role = $request->validated('role');
        $user->save();

        return response()->json($user);
    }

    public function block(
        BlockUserRequest $request,
        User $user
    ): JsonResponse {
        $this->authorize('update', $user);

        $user->is_blocked = $request->boolean('is_blocked');
        $user->save();

        return response()->json($user);
    }
}