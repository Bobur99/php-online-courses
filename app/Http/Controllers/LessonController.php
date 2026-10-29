<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\LessonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonController extends Controller
{
    public function __construct(private LessonService $lessonService) {}

    public function index(Course $course): JsonResponse
    {
        return response()->json([
            'data' => $this->lessonService->forCourse($course, auth('sanctum')->user()),
        ]);
    }

    public function store(LessonRequest $request, Course $course): JsonResponse
    {
        $this->authorize('create', [Lesson::class, $course]);

        return response()->json($this->lessonService->create(
            $course,
            $request->safe()->except('file'),
            $request->file('file'),
        ), 201);
    }

    public function update(LessonRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->lessonService->update(
            $lesson,
            $request->safe()->except('file'),
            $request->file('file'),
        ));
    }

    public function material(Lesson $lesson): BinaryFileResponse
    {
        Gate::authorize('viewMaterial', $lesson);

        return response()->file($this->lessonService->materialPath($lesson), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        $this->lessonService->delete($lesson);

        return response()->json(status: 204);
    }
}
