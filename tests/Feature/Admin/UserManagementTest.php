<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'role' => 'admin',
        ])->save();

        return $admin->refresh();
    }

    public function test_admin_can_view_users(): void
    {
        $admin = $this->createAdmin();

        User::factory()->count(2)->create();

        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_change_user_role(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'role' => 'student',
        ]);

        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/admin/users/{$user->id}/role", [
                'role' => 'teacher',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'teacher',
        ]);
    }

    public function test_admin_can_block_user(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'is_blocked' => false,
        ]);

        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/admin/users/{$user->id}/block", [
                'is_blocked' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_blocked' => true,
        ]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $teacher = User::factory()->create();

        $teacher->forceFill([
            'role' => 'teacher',
        ])->save();

        $teacher->refresh();

        $target = User::factory()->create();

        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'admin',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_change_own_role_or_block_self(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('api-token');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'teacher'])
            ->assertForbidden();

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/admin/users/{$admin->id}/block", ['is_blocked' => true])
            ->assertForbidden();
    }
}
