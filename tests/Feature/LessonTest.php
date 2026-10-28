<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonTest extends TestCase
{
    use RefreshDatabase;

    private function createTeacher(): User
    {
        $teacher = User::factory()->create();

        $teacher->forceFill([
            'role' => 'teacher',
        ])->save();

        return $teacher->refresh();
    }

    private function createCourse(User $teacher): Course
    {
        $category = Category::create([
            'name' => 'Backend',
        ]);

        return Course::create([
            'title' => 'Laravel Course',
            'description' => 'Course description',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);
    }

    private function lessonData(int $courseId, int $order = 1): array
    {
        return [
            'course_id' => $courseId,
            'title' => "Lesson {$order}",
            'content' => 'Lesson content',
            'order_number' => $order,
        ];
    }

    public function test_course_author_can_create_lesson(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson(
                "/api/courses/{$course->id}/lessons",
                $this->lessonData($course->id)
            )
            ->assertCreated()
            ->assertJsonPath('title', 'Lesson 1');

        $this->assertDatabaseHas('lessons', [
            'course_id' => $course->id,
            'order_number' => 1,
        ]);
    }

    public function test_course_author_can_attach_file_to_lesson(): void
    {
        Storage::fake('local');
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->post("/api/courses/{$course->id}/lessons", [
                ...$this->lessonData($course->id),
                'file' => UploadedFile::fake()->create('lesson.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertStringStartsWith('lessons/', $lesson->file_path);
        Storage::disk('local')->assertExists($lesson->file_path);
    }

    public function test_course_author_can_attach_image_to_lesson(): void
    {
        Storage::fake('local');
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->post("/api/courses/{$course->id}/lessons", [
                ...$this->lessonData($course->id),
                'file' => UploadedFile::fake()->create('lesson.png', 100, 'image/png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $lesson = Lesson::query()->where('course_id', $course->id)->firstOrFail();
        Storage::disk('local')->assertExists($lesson->file_path);
    }

    public function test_course_author_can_update_lesson(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);

        $lesson = Lesson::create($this->lessonData($course->id));
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/lessons/{$lesson->id}", [
                'title' => 'Updated Lesson',
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Updated Lesson');
    }

    public function test_course_author_can_delete_lesson(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);

        $lesson = Lesson::create($this->lessonData($course->id));
        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->deleteJson("/api/lessons/{$lesson->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('lessons', [
            'id' => $lesson->id,
        ]);
    }

    public function test_other_teacher_cannot_manage_foreign_course_lesson(): void
    {
        $owner = $this->createTeacher();
        $otherTeacher = $this->createTeacher();
        $course = $this->createCourse($owner);

        $lesson = Lesson::create($this->lessonData($course->id));
        $token = $otherTeacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/lessons/{$lesson->id}", [
                'title' => 'Should Fail',
            ])
            ->assertForbidden();
    }

    public function test_course_lessons_can_be_listed(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);

        Lesson::create($this->lessonData($course->id, 1));
        Lesson::create($this->lessonData($course->id, 2));

        $this
            ->getJson("/api/courses/{$course->id}/lessons")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_public_lesson_listing_hides_material_until_purchase(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);
        $lesson = Lesson::create([
            ...$this->lessonData($course->id),
            'file_path' => 'lessons/intro.pdf',
        ]);

        $this
            ->getJson("/api/courses/{$course->id}/lessons")
            ->assertOk()
            ->assertJsonPath('data.0.id', $lesson->id)
            ->assertJsonPath('data.0.file_path', null)
            ->assertJsonPath('data.0.has_material', true);
    }

    public function test_paid_student_can_access_lesson_material(): void
    {
        $teacher = $this->createTeacher();
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->createCourse($teacher);
        $lesson = Lesson::create([
            ...$this->lessonData($course->id),
            'file_path' => 'lessons/intro.pdf',
        ]);
        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'paid',
        ]);
        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->getJson("/api/courses/{$course->id}/lessons")
            ->assertOk()
            ->assertJsonPath('data.0.file_path', 'lessons/intro.pdf')
            ->assertJsonPath('data.0.has_material', true);
    }

    public function test_lesson_order_is_unique_inside_course(): void
    {
        $teacher = $this->createTeacher();
        $course = $this->createCourse($teacher);
        $token = $teacher->createToken('api-token');

        Lesson::create($this->lessonData($course->id, 1));

        $this
            ->withToken($token->plainTextToken)
            ->postJson(
                "/api/courses/{$course->id}/lessons",
                $this->lessonData($course->id, 1)
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_number']);
    }
}
