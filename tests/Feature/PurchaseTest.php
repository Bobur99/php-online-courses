<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): User
    {
        $student = User::factory()->create();

        $student->forceFill([
            'role' => 'student',
        ])->save();

        return $student->refresh();
    }

    private function createTeacher(): User
    {
        $teacher = User::factory()->create();

        $teacher->forceFill([
            'role' => 'teacher',
        ])->save();

        return $teacher->refresh();
    }

    private function createCourse(User $teacher, string $status = 'published'): Course
    {
        $category = Category::create([
            'name' => fake()->unique()->words(2, true),
        ]);

        return Course::create([
            'title' => 'Laravel API Course',
            'description' => 'Course description',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => $status,
        ]);
    }

    public function test_student_can_purchase_published_course(): void
    {
        config(['services.payment_provider.return_url' => 'http://courses.test']);

        Http::fake([
            '*' => Http::response([
                'operation_id' => 'operation-123',
                'payment_url' => 'https://payment.test/pay/operation-123',
            ], 201),
        ]);

        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);

        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/courses/{$course->id}/purchase")
            ->assertCreated()
            ->assertJson([
                'payment_url' => 'https://payment.test/pay/operation-123',
            ]);

        $this->assertDatabaseHas('purchases', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => 100000,
            'status' => 'pending',
            'provider_operation_id' => 'operation-123',
        ]);

        Http::assertSent(fn ($request): bool => $request['return_url'] === "http://courses.test/courses/{$course->id}");
    }

    public function test_student_cannot_purchase_draft_course(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher, 'draft');

        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/courses/{$course->id}/purchase")
            ->assertUnprocessable();

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_non_student_cannot_purchase_course(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);

        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/courses/{$course->id}/purchase")
            ->assertForbidden();

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_student_cannot_create_duplicate_purchase(): void
    {
        Http::fake([
            '*' => Http::response([
                'operation_id' => 'operation-123',
                'payment_url' => 'https://payment.test/pay/operation-123',
            ], 201),
        ]);

        $student = $this->createStudent();
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);

        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'pending',
        ]);

        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/courses/{$course->id}/purchase")
            ->assertUnprocessable();

        $this->assertDatabaseCount('purchases', 1);
    }
}
