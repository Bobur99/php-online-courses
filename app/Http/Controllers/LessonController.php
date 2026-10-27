<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
    public function index(Course $course): JsonResponse
    {
        $user = auth('sanctum')->user();
        $completedLessonIds = $user?->role === 'student'
            ? LessonProgress::query()
                ->where('student_id', $user->id)
                ->where('is_completed', true)
                ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
                ->pluck('lesson_id')
                ->all()
            : [];
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
                ->map(function (Lesson $lesson) use ($canAccessMaterials, $completedLessonIds): array {
                    $data = $lesson->toArray();
                    $data['has_material'] = filled($lesson->file_path);
                    $data['is_completed'] = in_array($lesson->id, $completedLessonIds, true);

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
