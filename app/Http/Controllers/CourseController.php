<?php

namespace App\Http\Controllers;

use App\Events\CoursePublished;
use App\Http\Requests\CourseRequest;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function adminIndex(): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        return response()->json([
            'data' => Course::query()
                ->with(['category', 'author:id,name,email'])
                ->withCount('lessons')
                ->latest()
                ->get(),
        ]);
    }

    public function teacherIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'teacher', 403);

        return response()->json([
            'data' => Course::query()
                ->where('author_id', $request->user()->id)
                ->with(['category', 'author:id,name,email'])
                ->withCount('lessons')
                ->latest()
                ->get(),
        ]);
    }

    public function studentIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $studentId = $request->user()->id;

        $courses = Course::query()
            ->whereHas('purchases', function ($query) use ($studentId): void {
                $query
                    ->where('student_id', $studentId)
                    ->where('status', 'paid');
            })
            ->with(['category', 'author:id,name'])
            ->withCount('lessons')
            ->withCount([
                'lessons as completed_lessons_count' => function ($query) use ($studentId): void {
                    $query->whereHas('progress', function ($progressQuery) use ($studentId): void {
                        $progressQuery
                            ->where('student_id', $studentId)
                            ->where('is_completed', true);
                    });
                },
            ])
            ->latest()
            ->get()
            ->map(function (Course $course): array {
                $totalLessons = (int) $course->lessons_count;
                $completedLessons = (int) $course->completed_lessons_count;
                $percentage = $totalLessons > 0
                    ? (int) round(($completedLessons / $totalLessons) * 100)
                    : 0;

                return [
                    ...$course->toArray(),
                    'progress_percentage' => $percentage,
                    'is_completed' => $totalLessons > 0 && $completedLessons === $totalLessons,
                ];
            });

        return response()->json(['data' => $courses]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Course::query()
            ->where('status', 'published');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where('title', 'ilike', '%'.$search.'%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('author_id', $request->integer('teacher_id'));
        }

        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc');

        $allowedSorts = ['price', 'created_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return response()->json([
            'data' => $query
                ->with(['category:id,name', 'author:id,name'])
                ->orderBy($sort, $direction)
                ->orderBy('id', $direction)
                ->get(),
        ]);
    }

    public function show(Course $course): JsonResponse
    {
        $user = auth('sanctum')->user();
        $isPurchased = $user?->purchases()
            ->where('course_id', $course->id)
            ->where('status', 'paid')
            ->exists() ?? false;
        abort_unless($course->status === 'published' || $isPurchased
            || $user?->role === 'admin'
            || ($user?->role === 'teacher' && $course->author_id === $user->id), 404);

        return response()->json([
            ...$course->load(['category', 'author:id,name,email'])->toArray(),
            'is_purchased' => $isPurchased,
        ]);
    }

    public function store(CourseRequest $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        $course = Course::create([
            ...$request->validated(),
            'author_id' => $request->user()->id,
            'status' => 'draft',
        ]);

        return response()->json($this->managementCourse($course), 201);
    }

    public function update(
        CourseRequest $request,
        Course $course
    ): JsonResponse {
        $this->authorize('update', $course);

        $course->update($request->validated());

        return response()->json($this->managementCourse($course));
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        $course->delete();

        return response()->json(status: 204);
    }

    public function publish(Course $course): JsonResponse
    {
        $this->authorize('publish', $course);

        $course->status = 'published';
        $course->save();

        CoursePublished::dispatch($course->refresh());

        return response()->json($this->managementCourse($course));
    }

    public function unpublish(Course $course): JsonResponse
    {
        $this->authorize('publish', $course);

        $course->status = 'draft';
        $course->save();

        return response()->json($this->managementCourse($course));
    }

    private function managementCourse(Course $course): Course
    {
        return $course
            ->refresh()
            ->load(['category', 'author:id,name,email'])
            ->loadCount('lessons');
    }
}
