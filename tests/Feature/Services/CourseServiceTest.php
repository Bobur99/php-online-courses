<?php

namespace Tests\Feature\Services;

use App\Events\CoursePublished;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CourseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_combines_filters_and_keeps_zero_filter_values(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => 'Backend']);
        $data = ['description' => 'Описание', 'price' => 100, 'category_id' => $category->id,
            'author_id' => $teacher->id, 'status' => 'published'];
        $course = Course::create([...$data, 'title' => 'Laravel 0']);
        Course::create([...$data, 'title' => 'PHP']);
        Course::create([...$data, 'title' => 'Laravel 0 draft', 'status' => 'draft']);
        Course::create([...$data, 'title' => 'Laravel 0 other', 'author_id' => User::factory()->create()->id]);
        $service = app(CourseService::class);

        $courses = $service->catalog(['search' => '0', 'category_id' => $category->id, 'teacher_id' => $teacher->id]);

        $this->assertSame([$course->id], $courses->modelKeys());
        $this->assertCount(0, $service->catalog(['category_id' => 0]));
    }

    public function test_publication_returns_author_and_dispatches_event_but_unpublication_does_not(): void
    {
        Event::fake([CoursePublished::class]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => 'Backend']);
        $service = app(CourseService::class);
        $course = $service->create($teacher, ['title' => 'Laravel', 'description' => 'Описание',
            'price' => 100, 'category_id' => $category->id]);

        $this->assertSame('draft', $course->status);
        $published = $service->publish($course);
        $this->assertSame('published', $published->status);
        $this->assertSame($teacher->id, $published->author->id);
        $this->assertSame(0, $published->lessons_count);
        Event::assertDispatchedTimes(CoursePublished::class, 1);

        $this->assertSame('draft', $service->unpublish($course)->status);
        Event::assertDispatchedTimes(CoursePublished::class, 1);
    }
}
