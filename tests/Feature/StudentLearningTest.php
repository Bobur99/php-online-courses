<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_purchased_courses_with_progress(): void
    {
        [$student, $course, $lessons] = $this->createPurchasedCourse();

        LessonProgress::create([
            'student_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherCourse = $this->createCourse();
        Purchase::create([
            'student_id' => $otherStudent->id,
            'course_id' => $otherCourse->id,
            'amount' => $otherCourse->price,
            'status' => 'paid',
        ]);

        $response = $this
            ->actingAs($student, 'sanctum')
            ->getJson('/api/student/courses');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.lessons_count', 2)
            ->assertJsonPath('data.0.completed_lessons_count', 1)
            ->assertJsonPath('data.0.progress_percentage', 50)
            ->assertJsonPath('data.0.is_completed', false);
    }

    public function test_non_student_cannot_view_student_courses(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this
            ->actingAs($teacher, 'sanctum')
            ->getJson('/api/student/courses')
            ->assertForbidden();
    }

    public function test_student_can_view_only_own_certificates(): void
    {
        [$student, $course] = $this->createPurchasedCourse();
        $certificate = Certificate::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'file_path' => 'certificates/own.pdf',
        ]);

        $otherStudent = User::factory()->create(['role' => 'student']);
        Certificate::create([
            'student_id' => $otherStudent->id,
            'course_id' => $course->id,
            'file_path' => 'certificates/other.pdf',
        ]);

        $this
            ->actingAs($student, 'sanctum')
            ->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $certificate->id)
            ->assertJsonPath('data.0.course.id', $course->id)
            ->assertJsonPath('data.0.course.title', $course->title)
            ->assertJsonPath('data.0.download_url', "/api/certificates/{$certificate->id}/download");
    }

    public function test_lesson_list_marks_completed_lessons_for_student(): void
    {
        [$student, $course, $lessons] = $this->createPurchasedCourse();
        LessonProgress::create([
            'student_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this
            ->actingAs($student, 'sanctum')
            ->getJson("/api/courses/{$course->id}/lessons")
            ->assertOk()
            ->assertJsonPath('data.0.is_completed', true)
            ->assertJsonPath('data.1.is_completed', false);
    }

    /** @return array{User, Course, array{Lesson, Lesson}} */
    private function createPurchasedCourse(): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->createCourse();
        $lessons = [
            Lesson::create([
                'course_id' => $course->id,
                'title' => 'Введение',
                'content' => 'Первый урок',
                'order_number' => 1,
            ]),
            Lesson::create([
                'course_id' => $course->id,
                'title' => 'Практика',
                'content' => 'Второй урок',
                'order_number' => 2,
            ]),
        ];

        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'paid',
        ]);

        return [$student, $course, $lessons];
    }

    private function createCourse(): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => fake()->unique()->word()]);

        return Course::create([
            'title' => fake()->unique()->sentence(3),
            'description' => 'Описание курса',
            'price' => 150000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);
    }
}
