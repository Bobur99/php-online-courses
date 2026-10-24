<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'role' => 'admin',
        ])->save();

        return $admin->refresh();
    }

    public function test_anyone_can_view_categories(): void
    {
        Category::factory()->count(2)->create();

        $this
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/categories', [
                'name' => 'PHP Development',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'PHP Development');

        $this->assertDatabaseHas('categories', [
            'name' => 'PHP Development',
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create([
            'name' => 'Old Name',
        ]);

        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create();

        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_non_admin_cannot_manage_categories(): void
    {
        $teacher = User::factory()->create();

        $teacher->forceFill([
            'role' => 'teacher',
        ])->save();

        $teacher->refresh();

        $token = $teacher->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/categories', [
                'name' => 'PHP Development',
            ])
            ->assertForbidden();
    }

    public function test_category_name_is_required_and_unique(): void
    {
        $admin = $this->createAdmin();
        $category = Category::factory()->create([
            'name' => 'PHP Development',
        ]);

        $token = $admin->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/categories', [
                'name' => $category->name,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}