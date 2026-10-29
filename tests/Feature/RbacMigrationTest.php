<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbacMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_and_rollback_preserve_existing_users_and_learning_data(): void
    {
        $migration = require database_path('migrations/2026_10_29_000000_create_rbac_tables.php');
        $migration->down();
        $users = collect(['admin', 'teacher', 'student'])->mapWithKeys(
            fn (string $role) => [$role => User::factory()->create(['role' => $role])]
        );
        $course = Course::create(['title' => 'Existing course', 'description' => 'Description', 'price' => 100,
            'author_id' => $users['teacher']->id, 'category_id' => Category::create(['name' => 'Existing'])->id,
            'status' => 'published']);
        $lesson = $course->lessons()->create(['title' => 'Lesson', 'content' => 'Content', 'order_number' => 1]);
        Purchase::create(['student_id' => $users['student']->id, 'course_id' => $course->id,
            'amount' => 100, 'status' => 'paid', 'provider_operation_id' => 'existing-operation']);
        LessonProgress::create(['student_id' => $users['student']->id, 'lesson_id' => $lesson->id,
            'is_completed' => true, 'completed_at' => now()]);
        Certificate::create(['student_id' => $users['student']->id, 'course_id' => $course->id,
            'file_path' => 'certificates/existing.pdf']);
        $before = $this->existingData();

        $migration->up();

        $this->assertSame($before, $this->existingData());
        foreach ($users as $role => $user) {
            $this->assertSame($role, $user->refresh()->roleDefinition->name);
        }
        $this->assertTrue($users['teacher']->can('courses.create'));
        $this->assertTrue($users['student']->can('courses.study'));

        $migration->down();
        $this->assertSame($before, $this->existingData());
        $this->assertFalse(Schema::hasTable('roles'));
    }

    public function test_unknown_legacy_role_fails_upgrade_atomically_without_rewriting_users(): void
    {
        $migration = require database_path('migrations/2026_10_29_000000_create_rbac_tables.php');
        $migration->down();
        $user = User::factory()->create(['role' => 'legacy-unknown']);

        try {
            DB::transaction(fn () => $migration->up());
            $this->fail('An unknown role must not be silently reassigned.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }

        $this->assertSame('legacy-unknown', $user->refresh()->role);
        $this->assertFalse(Schema::hasTable('roles'));
    }

    private function existingData(): array
    {
        $data = [];
        foreach (['users', 'categories', 'courses', 'lessons', 'purchases', 'lesson_progress', 'certificates'] as $table) {
            $data[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $data;
    }
}
