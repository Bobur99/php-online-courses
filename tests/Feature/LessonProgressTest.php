<?php

namespace Tests\Feature;

use App\Events\CourseCompleted;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LessonProgressTest extends TestCase
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

    private function createCourseWithLessons(User $student): array
    {
        $teacher = User::factory()->create();

        $teacher->forceFill([
            'role' => 'teacher',
        ])->save();

        $category = Category::create([
            'name' => 'Backend',
        ]);

        $course = Course::create([
            'title' => 'Laravel Course',
            'description' => 'Course description',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);

        $lessonOne = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'content' => 'Content 1',
            'order_number' => 1,
        ]);

        $lessonTwo = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson 2',
            'content' => 'Content 2',
            'order_number' => 2,
        ]);

        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'paid',
            'provider_operation_id' => 'operation-'.$student->id,
        ]);

        return [$course, $lessonOne, $lessonTwo];
    }

    public function test_student_can_complete_lesson(): void
    {
        $student = $this->createStudent();
        [, $lesson] = $this->createCourseWithLessons($student);
        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('lesson_progress', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
        ]);
    }

    public function test_duplicate_completion_does_not_create_duplicate_progress(): void
    {
        $student = $this->createStudent();
        [, $lesson] = $this->createCourseWithLessons($student);
        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk();

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk();

        $this->assertDatabaseCount('lesson_progress', 1);
    }

    public function test_student_without_purchase_cannot_complete_lesson(): void
    {
        $student = $this->createStudent();
        $teacher = User::factory()->create();

        $teacher->forceFill([
            'role' => 'teacher',
        ])->save();

        $category = Category::create([
            'name' => 'Backend',
        ]);

        $course = Course::create([
            'title' => 'Unpurchased Course',
            'description' => 'Course description',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);

        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'content' => 'Content',
            'order_number' => 1,
        ]);

        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertForbidden();
    }

    public function test_course_completed_event_is_dispatched_after_all_lessons(): void
    {
        Event::fake();

        $student = $this->createStudent();
        [, $lessonOne, $lessonTwo] = $this->createCourseWithLessons($student);
        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/lessons/{$lessonOne->id}/complete")
            ->assertOk();

        $this
            ->withToken($token->plainTextToken)
            ->postJson("/api/lessons/{$lessonTwo->id}/complete")
            ->assertOk();

        Event::assertDispatched(CourseCompleted::class);
    }
}