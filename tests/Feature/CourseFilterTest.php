<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseFilterTest extends TestCase
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

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name,
        ]);
    }

    private function createPublishedCourse(
        string $title,
        int $price,
        Category $category,
        User $teacher
    ): Course {
        return Course::create([
            'title' => $title,
            'description' => 'Course description',
            'price' => $price,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);
    }

    public function test_courses_can_be_searched_by_title(): void
    {
        $teacher = $this->createTeacher();
        $category = $this->createCategory('Backend');

        $this->createPublishedCourse(
            'Laravel API Development',
            100000,
            $category,
            $teacher
        );

        $this->createPublishedCourse(
            'JavaScript Basics',
            80000,
            $category,
            $teacher
        );

        $this
            ->getJson('/api/courses?search=Laravel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel API Development');
    }

    public function test_courses_can_be_filtered_by_category(): void
    {
        $teacher = $this->createTeacher();
        $backend = $this->createCategory('Backend');
        $frontend = $this->createCategory('Frontend');

        $this->createPublishedCourse(
            'Laravel API',
            100000,
            $backend,
            $teacher
        );

        $this->createPublishedCourse(
            'JavaScript UI',
            80000,
            $frontend,
            $teacher
        );

        $this
            ->getJson("/api/courses?category_id={$backend->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Laravel API');
    }

    public function test_courses_can_be_filtered_by_teacher(): void
    {
        $teacherOne = $this->createTeacher();
        $teacherTwo = $this->createTeacher();
        $category = $this->createCategory('Backend');

        $this->createPublishedCourse(
            'Teacher One Course',
            100000,
            $category,
            $teacherOne
        );

        $this->createPublishedCourse(
            'Teacher Two Course',
            80000,
            $category,
            $teacherTwo
        );

        $this
            ->getJson("/api/courses?teacher_id={$teacherOne->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Teacher One Course');
    }

    public function test_courses_can_be_sorted_by_price(): void
    {
        $teacher = $this->createTeacher();
        $category = $this->createCategory('Backend');

        $this->createPublishedCourse('Expensive Course', 200000, $category, $teacher);
        $this->createPublishedCourse('Cheap Course', 50000, $category, $teacher);

        $this
            ->getJson('/api/courses?sort=price&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Cheap Course')
            ->assertJsonPath('data.1.title', 'Expensive Course');
    }

    public function test_courses_can_be_sorted_by_creation_date(): void
    {
        $teacher = $this->createTeacher();
        $category = $this->createCategory('Backend');

        $first = $this->createPublishedCourse(
            'First Course',
            100000,
            $category,
            $teacher
        );

        $second = $this->createPublishedCourse(
            'Second Course',
            100000,
            $category,
            $teacher
        );

        $this
            ->getJson('/api/courses?sort=created_at&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_courses_can_be_sorted_by_creation_date_oldest_first(): void
    {
        $teacher = $this->createTeacher();
        $category = $this->createCategory('Backend');

        $first = $this->createPublishedCourse('First Course', 100000, $category, $teacher);
        $second = $this->createPublishedCourse('Second Course', 100000, $category, $teacher);

        $this
            ->getJson('/api/courses?sort=created_at&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_catalog_combines_filters_and_handles_empty_and_zero_input(): void
    {
        $teacher = $this->createTeacher();
        $otherTeacher = $this->createTeacher();
        $category = $this->createCategory('Backend');
        $course = $this->createPublishedCourse('Laravel 0', 100, $category, $teacher);
        $this->createPublishedCourse('PHP', 200, $category, $teacher);
        $this->createPublishedCourse('Laravel 0 other', 300, $category, $otherTeacher);

        $this->getJson("/api/courses?search=0&category_id={$category->id}&teacher_id={$teacher->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $course->id);
        $this->getJson('/api/courses?category_id=0')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/courses?search=&category_id=&teacher_id=&sort=invalid&direction=invalid')
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.2.id', $course->id);
    }
}
