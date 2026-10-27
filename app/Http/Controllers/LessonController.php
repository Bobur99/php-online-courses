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
        $user = auth('sanctum')->user();
        $canAccessMaterials = $user !== null
            && (
                $user->role === 'admin'
                || ($user->role === 'teacher' && $course->author_id === $user->id)
                || $course->purchases()
                    ->where('student_id', $user->id)
                    ->where('status', 'paid')
                    ->exists()
            );

        return response()->json([
            'data' => $course->lessons()
                ->orderBy('order_number')
                ->get()
                ->map(function (Lesson $lesson) use ($canAccessMaterials): array {
                    $data = $lesson->toArray();
                    $data['has_material'] = filled($lesson->file_path);

                    if (! $canAccessMaterials) {
                        $data['file_path'] = null;
                    }

                    return $data;
                }),
        ]);
    }

    public function store(
        LessonRequest $request,
        Course $course
    ): JsonResponse {
        $this->authorize('update', $course);

        $data = $request->validated();
        unset($data['file']);
        $data['file_path'] = $request->hasFile('file')
            ? $request->file('file')->store('lessons', 'public')
            : null;

        $lesson = $course->lessons()->create($data);

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
