<?php

namespace App\Services;

use App\Events\CoursePublished;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class CourseService
{
    /** @return Collection<int, Course> */
    public function forAdministration(): Collection
    {
        return Course::query()
            ->with(['category', 'author:id,name,email'])
            ->withCount('lessons')
            ->latest()
            ->get();
    }

    /** @return Collection<int, Course> */
    public function forTeacher(User $teacher): Collection
    {
        return Course::query()
            ->where('author_id', $teacher->id)
            ->with(['category', 'author:id,name,email'])
            ->withCount('lessons')
            ->latest()
            ->get();
    }

    public function forStudent(User $student): SupportCollection
    {
        $studentId = $student->id;

        return Course::query()
            ->whereHas('purchases', function ($query) use ($studentId): void {
                $query->where('student_id', $studentId)->where('status', 'paid');
            })
            ->with(['category', 'author:id,name'])
            ->withCount('lessons')
            ->withCount([
                'lessons as completed_lessons_count' => function ($query) use ($studentId): void {
                    $query->whereHas('progress', function ($progressQuery) use ($studentId): void {
                        $progressQuery->where('student_id', $studentId)->where('is_completed', true);
                    });
                },
            ])
            ->latest()
            ->get()
            ->map(function (Course $course): array {
                $totalLessons = (int) $course->lessons_count;
                $completedLessons = (int) $course->completed_lessons_count;

                return [
                    ...$course->toArray(),
                    'progress_percentage' => $totalLessons > 0
                        ? (int) round(($completedLessons / $totalLessons) * 100) : 0,
                    'is_completed' => $totalLessons > 0 && $completedLessons === $totalLessons,
                ];
            });
    }

    /** @return Collection<int, Course> */
    public function catalog(array $filters): Collection
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        if (! in_array($sort, ['price', 'created_at'], true)) {
            $sort = 'created_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return Course::query()
            ->where('status', 'published')
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $query->where('title', 'ilike', '%'.$filters['search'].'%');
            })
            ->when(isset($filters['category_id']), function ($query) use ($filters): void {
                $query->where('category_id', $filters['category_id']);
            })
            ->when(isset($filters['teacher_id']), function ($query) use ($filters): void {
                $query->where('author_id', $filters['teacher_id']);
            })
            ->with(['category:id,name', 'author:id,name'])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->get();
    }

    public function details(Course $course, ?User $user): array
    {
        $isPurchased = $user?->purchases()
            ->where('course_id', $course->id)
            ->where('status', 'paid')
            ->exists() ?? false;

        abort_unless($course->status === 'published' || $isPurchased
            || $user?->role === 'admin'
            || ($user?->role === 'teacher' && $course->author_id === $user->id), 404);

        return [
            ...$course->load(['category', 'author:id,name,email'])->toArray(),
            'is_purchased' => $isPurchased,
        ];
    }

    public function create(User $author, array $data): Course
    {
        $course = Course::create([
            ...$data,
            'author_id' => $author->id,
            'status' => 'draft',
        ]);

        return $this->managementCourse($course);
    }

    public function update(Course $course, array $data): Course
    {
        $course->update($data);

        return $this->managementCourse($course);
    }

    public function delete(Course $course): void
    {
        $course->delete();
    }

    public function publish(Course $course): Course
    {
        $course->status = 'published';
        $course->save();

        CoursePublished::dispatch($course->refresh());

        return $this->managementCourse($course);
    }

    public function unpublish(Course $course): Course
    {
        $course->status = 'draft';
        $course->save();

        return $this->managementCourse($course);
    }

    private function managementCourse(Course $course): Course
    {
        return $course->refresh()
            ->load(['category', 'author:id,name,email'])
            ->loadCount('lessons');
    }
}
