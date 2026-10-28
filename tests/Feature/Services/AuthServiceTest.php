<?php

namespace Tests\Feature\Services;

use App\Events\UserRegistered;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_hashes_password_and_creates_student_with_token(): void
    {
        Event::fake([UserRegistered::class]);

        $result = app(AuthService::class)->register([
            'name' => 'Анна',
            'email' => 'anna@example.com',
            'password' => 'test-password',
        ]);

        $user = $result['user']->refresh();
        $this->assertSame('student', $user->role);
        $this->assertFalse($user->is_blocked);
        $this->assertTrue(Hash::check('test-password', $user->password));
        $this->assertNotEmpty($result['token']);
        $this->assertSame(1, $user->tokens()->count());
        Event::assertDispatched(UserRegistered::class, fn ($event) => $event->user->is($user));
    }

    public function test_credentials_are_verified_without_issuing_a_token(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $service = app(AuthService::class);

        $this->assertTrue($service->authenticate($user->email, 'test-password')->is($user));
        $this->assertNull($service->authenticate($user->email, 'wrong-password'));
        $this->assertNull($service->authenticate('missing@example.com', 'test-password'));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_incorrect_current_password_leaves_password_unchanged(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $originalHash = $user->password;

        $this->assertFalse(app(UserService::class)->changePassword($user, 'wrong-password', 'new-password'));
        $this->assertSame($originalHash, $user->refresh()->password);
    }
}
