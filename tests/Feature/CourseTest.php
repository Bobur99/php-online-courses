<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Events\CoursePublished;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => $role,
        ])->save();

        return $user->refresh();
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'PHP Development',
        ]);
    }

    private function courseData(Category $category, User $author): array
    {
        return [
            'title' => 'Laravel API Course',
            'description' => 'Backend development with Laravel.',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $author->id,
        ];
    }

    public function test_teacher_can_create_course(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $category = $this->createCategory();
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/courses', $this->courseData($category, $teacher))
            ->assertCreated()
            ->assertJsonPath('title', 'Laravel API Course');

        $this->assertDatabaseHas('courses', [
            'title' => 'Laravel API Course',
            'author_id' => $teacher->id,
            'status' => 'draft',
        ]);
    }

    public function test_student_cannot_create_course(): void
    {
        $student = $this->createUserWithRole('student');
        $category = $this->createCategory();
        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/courses', $this->courseData($category, $student))
            ->assertForbidden();
    }

    public function test_author_can_update_own_course(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $category = $this->createCategory();

        $course = Course::create($this->courseData($category, $teacher));
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/courses/{$course->id}", [
                'title' => 'Updated Laravel Course',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Updated Laravel Course');
    }

    public function test_teacher_cannot_update_foreign_course(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $otherTeacher = $this->createUserWithRole('teacher');
        $category = $this->createCategory();

        $course = Course::create($this->courseData($category, $otherTeacher));
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/courses/{$course->id}", [
                'title' => 'Should Fail',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_course(): void
    {
        $admin = $this->createUserWithRole('admin');
        $teacher = $this->createUserWithRole('teacher');
        $category = $this->createCategory();

        $course = Course::create($this->courseData($category, $teacher));
        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->deleteJson("/api/courses/{$course->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('courses', [
            'id' => $course->id,
        ]);
    }

    public function test_author_can_publish_course(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $category = $this->createCategory();

        $course = Course::create($this->courseData($category, $teacher));
        $token = $teacher->createToken('api-token');
        Event::fake();
        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/courses/{$course->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', 'published');

        Event::assertDispatched(
            CoursePublished::class,
            fn(CoursePublished $event): bool =>
            $event->course->is($course)
        );

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'published',
        ]);
    }

    public function test_anyone_can_view_published_courses(): void
    {
        $teacher = $this->createUserWithRole('teacher');
        $category = $this->createCategory();

        $course = Course::create($this->courseData($category, $teacher));

        $course->forceFill([
            'status' => 'published',
        ])->save();
        $this
            ->getJson('/api/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
