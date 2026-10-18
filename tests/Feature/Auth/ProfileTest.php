<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Student',
            'email' => 'student@example.com',
        ]);

        $token = $user->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', 'Test Student')
            ->assertJsonPath('email', 'student@example.com')
            ->assertJsonPath('role', 'student');
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this
            ->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_own_profile(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('email', 'updated@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_profile_update_cannot_change_role_or_block_status(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson('/api/profile', [
                'name' => 'Updated Name',
                'role' => 'admin',
                'is_blocked' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'student',
            'is_blocked' => false,
        ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $token = $user->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson('/api/profile/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(
            password_verify(
                'new-password',
                $user->refresh()->password
            )
        );
    }
}