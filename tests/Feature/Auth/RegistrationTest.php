<?php

namespace Tests\Feature\Auth;

use App\Events\UserRegistered;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_api_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'role' => 'student',
            'is_blocked' => false,
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $data = [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson('/api/auth/register', $data)
            ->assertCreated();

        $this->postJson('/api/auth/register', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_cannot_assign_role_or_block_status(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'is_blocked' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'role' => 'student',
            'is_blocked' => false,
        ]);
    }

    public function test_registration_hashes_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = \App\Models\User::first();

        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(
            'password123',
            $user->password
        ));
    }

    public function test_registration_dispatches_user_registered_event(): void
    {
        Event::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        Event::assertDispatched(UserRegistered::class);
    }
}
