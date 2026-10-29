<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Permission;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public static function permissionsByRole(): array
    {
        return [
            'administrator' => ['admin', [
                'users.view', 'users.update',
                'categories.create', 'categories.update', 'categories.delete',
                'courses.view-any', 'courses.create', 'courses.update-any',
                'courses.delete-any', 'courses.publish-any',
                'lessons.view-any', 'lessons.create-any', 'lessons.update-any', 'lessons.delete-any',
                'certificates.download-own',
            ]],
            'teacher' => ['teacher', [
                'courses.view-own', 'courses.create', 'courses.update-own',
                'courses.delete-own', 'courses.publish-own',
                'lessons.view-own', 'lessons.create-own', 'lessons.update-own', 'lessons.delete-own',
                'certificates.download-own',
            ]],
            'student' => ['student', [
                'courses.purchase', 'courses.study', 'lessons.complete',
                'certificates.view-own', 'certificates.download-own',
            ]],
        ];
    }

    #[DataProvider('permissionsByRole')]
    public function test_role_grants_only_its_existing_business_permissions(string $role, array $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->assertSame($role, $user->roleDefinition->name);
        $this->assertEqualsCanonicalizing($expected, $user->roleDefinition->permissions->pluck('name')->all());
        foreach (Permission::all() as $permission) {
            $this->assertSame(in_array($permission->name, $expected, true), $user->can($permission->name), $permission->name);
        }
        $this->assertFalse($user->can('undefined.permission'));
    }

    public function test_api_uses_database_permissions_and_revocation_takes_effect_immediately(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token = $student->createToken('rbac')->plainTextToken;
        $data = ['title' => 'RBAC course', 'description' => 'Description', 'price' => 100,
            'category_id' => Category::create(['name' => 'RBAC'])->id];

        $this->withToken($token)->postJson('/api/courses', $data)->assertForbidden();

        $permission = Permission::where('name', 'courses.create')->firstOrFail();
        $student->roleDefinition->permissions()->attach($permission);
        $this->withToken($token)->postJson('/api/courses', $data)->assertCreated()
            ->assertJsonPath('author_id', $student->id)->assertJsonPath('status', 'draft');

        $student->roleDefinition->permissions()->detach($permission);
        $this->withToken($token)->postJson('/api/courses', $data)->assertForbidden();
        $this->assertDatabaseCount('courses', 1);
    }

    public function test_teacher_permission_does_not_grant_access_to_another_authors_course(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => 'RBAC']);
        $data = ['title' => 'Original', 'description' => 'Description', 'price' => 100,
            'category_id' => $category->id, 'status' => 'draft'];
        $own = Course::create([...$data, 'author_id' => $teacher->id]);
        $foreign = Course::create([...$data, 'author_id' => $otherTeacher->id]);
        $this->withToken($teacher->createToken('rbac')->plainTextToken)
            ->patchJson("/api/courses/{$own->id}", ['title' => 'Updated'])->assertOk();
        $this->patchJson("/api/courses/{$foreign->id}", ['title' => 'Forbidden'])->assertForbidden();

        $teacher->roleDefinition->permissions()->detach(Permission::where('name', 'courses.update-own')->firstOrFail());
        $this->patchJson("/api/courses/{$own->id}", ['title' => 'Revoked'])->assertForbidden();
        $this->assertSame('Updated', $own->refresh()->title);
        $this->assertSame('Original', $foreign->refresh()->title);
    }

    public function test_administrator_does_not_bypass_revoked_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($admin->can('users.view'));
        $admin->roleDefinition->permissions()->detach(Permission::where('name', 'users.view')->firstOrFail());

        $this->assertFalse($admin->can('users.view'));
        $this->withToken($admin->createToken('rbac')->plainTextToken)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_blocked_user_and_guest_have_no_rbac_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_blocked' => true]);
        $this->assertFalse($admin->can('users.view'));
        $this->assertFalse($admin->can('courses.create'));
        $this->assertFalse(Gate::forUser(null)->allows('courses.create'));
    }

    public function test_role_change_updates_permissions_for_an_existing_token_and_keeps_json_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $token = $student->createToken('rbac')->plainTextToken;
        $this->withToken($token)->getJson('/api/teacher/courses')->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->withToken($admin->createToken('rbac')->plainTextToken)
            ->patchJson("/api/admin/users/{$student->id}/role", ['role' => 'teacher'])
            ->assertOk()->assertJsonPath('role', 'teacher')->assertJsonMissingPath('role_definition');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/teacher/courses')->assertOk();
        $this->getJson('/api/student/courses')->assertForbidden();
        $this->getJson('/api/profile')->assertOk()->assertJsonPath('role', 'teacher')
            ->assertJsonMissingPath('role_definition')->assertJsonMissingPath('permissions');
    }

    public function test_default_role_seeding_is_idempotent(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(RbacSeeder::class);

        $this->assertSame(['admin', 'student', 'teacher'], Role::orderBy('name')->pluck('name')->all());
        foreach (self::permissionsByRole() as [$role, $expected]) {
            $this->assertEqualsCanonicalizing($expected, Role::where('name', $role)->firstOrFail()->permissions->pluck('name')->all());
        }
    }

    public function test_paid_course_materials_still_require_study_permission(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lessons/rbac.pdf', '%PDF-test');
        $student = User::factory()->create(['role' => 'student']);
        $lesson = $this->paidLesson($student);
        $lesson->update(['file_path' => 'lessons/rbac.pdf']);
        $token = $student->createToken('rbac')->plainTextToken;

        $this->withToken($token)->getJson("/api/courses/{$lesson->course_id}/lessons")
            ->assertOk()->assertJsonPath('data.0.content', 'Private content');
        $this->get("/api/lessons/{$lesson->id}/material")->assertOk();
        $student->roleDefinition->permissions()->detach(Permission::where('name', 'courses.study')->firstOrFail());

        $this->getJson("/api/courses/{$lesson->course_id}/lessons")->assertOk()
            ->assertJsonPath('data.0.content', null)->assertJsonPath('data.0.material_url', null);
        $this->getJson("/api/lessons/{$lesson->id}/material")->assertForbidden();
    }

    public function test_revoked_purchase_permission_does_not_contact_provider_or_create_purchase(): void
    {
        Http::fake();
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course();
        $student->roleDefinition->permissions()->detach(Permission::where('name', 'courses.purchase')->firstOrFail());

        $this->withToken($student->createToken('rbac')->plainTextToken)
            ->postJson("/api/courses/{$course->id}/purchase")->assertForbidden();

        $this->assertDatabaseCount('purchases', 0);
        Http::assertNothingSent();
    }

    public function test_revoked_completion_permission_cannot_change_progress(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $lesson = $this->paidLesson($student);
        $student->roleDefinition->permissions()->detach(Permission::where('name', 'lessons.complete')->firstOrFail());

        $this->withToken($student->createToken('rbac')->plainTextToken)
            ->postJson("/api/lessons/{$lesson->id}/complete")->assertForbidden();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_loaded_role_relationship_is_not_exposed_in_user_json(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $user->load('roleDefinition.permissions');

        $this->assertSame('teacher', $user->toArray()['role']);
        $this->assertArrayNotHasKey('role_definition', $user->toArray());
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    private function course(): Course
    {
        return Course::create(['title' => 'RBAC course', 'description' => 'Description', 'price' => 100,
            'category_id' => Category::create(['name' => 'RBAC'])->id,
            'author_id' => User::factory()->create(['role' => 'teacher'])->id, 'status' => 'published']);
    }

    private function paidLesson(User $student): Lesson
    {
        $course = $this->course();
        Purchase::create(['student_id' => $student->id, 'course_id' => $course->id, 'amount' => 100, 'status' => 'paid']);

        return $course->lessons()->create(['title' => 'Lesson', 'content' => 'Private content', 'order_number' => 1]);
    }
}
