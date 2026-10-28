<?php

namespace Tests\Feature\Services;

use App\Events\CourseCompleted;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Purchase;
use App\Models\User;
use App\Services\LessonProgressService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LessonProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create(['title' => 'Laravel', 'description' => 'Описание', 'price' => 100,
            'category_id' => Category::create(['name' => 'Backend'])->id,
            'author_id' => User::factory()->create(['role' => 'teacher'])->id, 'status' => 'published']);

        return $course->lessons()->create(['title' => 'Введение', 'content' => 'Материал', 'order_number' => 1]);
    }

    public function test_completing_last_lesson_twice_emits_only_one_completion_event(): void
    {
        Event::fake([CourseCompleted::class]);
        $student = User::factory()->create(['role' => 'student']);
        $lesson = $this->lesson();
        Purchase::create(['student_id' => $student->id, 'course_id' => $lesson->course_id,
            'amount' => 100, 'status' => 'paid']);
        $service = app(LessonProgressService::class);

        $first = $service->complete($student, $lesson);
        $second = $service->complete($student, $lesson);

        $this->assertTrue($first->is($second));
        $this->assertTrue($second->is_completed);
        $this->assertDatabaseCount('lesson_progress', 1);
        Event::assertDispatchedTimes(CourseCompleted::class, 1);
    }

    public function test_unpaid_student_cannot_record_progress_or_trigger_completion(): void
    {
        Event::fake([CourseCompleted::class]);
        $student = User::factory()->create(['role' => 'student']);
        $lesson = $this->lesson();
        $service = app(LessonProgressService::class);

        try {
            $service->complete($student, $lesson);
            $this->fail('An unpaid student must not complete a lesson.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('Course must be purchased first.', $exception->getMessage());
        }

        $this->assertDatabaseCount('lesson_progress', 0);
        Event::assertNotDispatched(CourseCompleted::class);
    }
}
