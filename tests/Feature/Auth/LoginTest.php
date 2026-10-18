<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'password123',
            'role' => 'student',
            'is_blocked' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'student@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
                'token',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'is_blocked' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertForbidden()
            ->assertJson([
                'message' => 'User is blocked.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }
}