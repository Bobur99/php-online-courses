<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        return response()->json([
            'data' => $course->lessons()
                ->orderBy('order_number')
                ->get(),
        ]);
    }

    public function store(
        LessonRequest $request,
        Course $course
    ): JsonResponse {
        $this->authorize('update', $course);

        $lesson = $course->lessons()->create([
            ...$request->validated(),
            'file_path' => null,
        ]);

        return response()->json($lesson, 201);
    }

    public function update(
        LessonRequest $request,
        Lesson $lesson
    ): JsonResponse {
        $this->authorize('update', $lesson);

        $lesson->update($request->validated());

        return response()->json($lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        $lesson->delete();

        return response()->json(status: 204);
    }
}