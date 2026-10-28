<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccessRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function course(string $status = 'published'): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => 'Regression']);

        return Course::create(['title' => 'Course', 'description' => 'Description', 'price' => 100,
            'author_id' => $teacher->id, 'category_id' => $category->id, 'status' => $status]);
    }

    private function lesson(Course $course, int $order = 1): Lesson
    {
        return $course->lessons()->create(['title' => 'Lesson', 'content' => 'PRIVATE_CONTENT', 'order_number' => $order]);
    }

    public function test_blocked_user_cannot_use_existing_token(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $token = $user->createToken('test')->plainTextToken;
        $user->forceFill(['is_blocked' => true])->save();
        $this->withToken($token)->getJson('/api/profile')->assertForbidden();
    }

    public function test_public_program_hides_content_and_draft_lessons(): void
    {
        $course = $this->course();
        $this->lesson($course);
        $this->getJson("/api/courses/{$course->id}/lessons")->assertOk()->assertDontSee('PRIVATE_CONTENT');
        $course->update(['status' => 'draft']);
        $this->getJson("/api/courses/{$course->id}/lessons")->assertNotFound();
    }

    public function test_duplicate_order_on_update_is_validation_error(): void
    {
        $course = $this->course();
        $this->lesson($course);
        $lesson = $this->lesson($course, 2);
        $this->withToken($course->author->createToken('test')->plainTextToken)
            ->patchJson("/api/lessons/{$lesson->id}", ['order_number' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('order_number');
    }

    public function test_replacing_file_saves_private_material(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $lesson = $this->lesson($course);
        $this->withToken($course->author->createToken('test')->plainTextToken)
            ->patch("/api/lessons/{$lesson->id}", ['file' => UploadedFile::fake()->create('material.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotNull($lesson->refresh()->file_path);
        Storage::disk('local')->assertExists($lesson->file_path);
    }

    public function test_material_requires_purchase_and_remains_available_if_course_unpublished(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lessons/private.pdf', '%PDF-private');
        $course = $this->course();
        $lesson = $this->lesson($course);
        $lesson->update(['file_path' => 'lessons/private.pdf']);
        $url = "/api/lessons/{$lesson->id}/material";
        $this->getJson($url)->assertUnauthorized();
        $student = User::factory()->create(['role' => 'student']);
        $this->withToken($student->createToken('test')->plainTextToken)->getJson($url)->assertForbidden();
        Purchase::create(['student_id' => $student->id, 'course_id' => $course->id, 'amount' => 100, 'status' => 'paid']);
        $course->update(['status' => 'draft']);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->getJson("/api/courses/{$course->id}")->assertOk()->assertJsonPath('is_purchased', true);
    }

    public function test_put_updates_profile(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->withToken($student->createToken('test')->plainTextToken)
            ->putJson('/api/profile', ['name' => 'Новое имя', 'email' => $student->email])->assertOk()->assertJsonPath('name', 'Новое имя');
    }

    public function test_legacy_materials_are_moved_without_losing_contents(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('lessons/legacy.pdf', '%PDF-legacy');
        $this->artisan('materials:privatize')->assertSuccessful();
        $this->assertSame('%PDF-legacy', Storage::disk('local')->get('lessons/legacy.pdf'));
        Storage::disk('public')->assertMissing('lessons/legacy.pdf');
        $this->artisan('materials:privatize')->assertSuccessful();
    }

    public function test_migration_preserves_both_files_on_conflict(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('lessons/legacy.pdf', 'original');
        Storage::disk('local')->put('lessons/legacy.pdf', 'different');
        $this->artisan('materials:privatize')->assertFailed();
        $this->assertSame('original', Storage::disk('public')->get('lessons/legacy.pdf'));
        $this->assertSame('different', Storage::disk('local')->get('lessons/legacy.pdf'));
    }

    public function test_deleting_used_category_returns_safe_conflict(): void
    {
        $course = $this->course();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->withToken($admin->createToken('test')->plainTextToken)
            ->deleteJson("/api/categories/{$course->category_id}")->assertConflict()->assertJsonMissingPath('exception');
    }

    public function test_unexpected_api_errors_do_not_expose_debug_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/test-error', function () {
            throw new \RuntimeException('INTERNAL_SECRET_DETAILS');
        });
        $this->getJson('/api/test-error')->assertStatus(500)->assertDontSee('INTERNAL_SECRET_DETAILS')->assertJsonMissingPath('trace');
    }
}
