<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $canAccessMaterials = $user !== null && (
            $user->role === 'admin'
            || ($user->role === 'teacher' && $course->author_id === $user->id)
            || ($user->role === 'student' && $course->purchases()
                ->where('student_id', $user->id)->where('status', 'paid')->exists())
        );
        abort_unless($course->status === 'published' || $canAccessMaterials, 404);

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
                        $data['content'] = null;
                    }
                    $data['material_url'] = $canAccessMaterials && $lesson->file_path
                        ? "/api/lessons/{$lesson->id}/material" : null;

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
            ? $request->file('file')->store('lessons', 'local')
            : null;

        $lesson = $course->lessons()->create($data);

        return response()->json($lesson, 201);
    }

    public function update(
        LessonRequest $request,
        Lesson $lesson
    ): JsonResponse {
        $this->authorize('update', $lesson);

        $data = $request->safe()->except('file');
        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('lessons', 'local');
        }
        $lesson->update($data);

        return response()->json($lesson);
    }

    public function material(Lesson $lesson): BinaryFileResponse
    {
        Gate::authorize('viewMaterial', $lesson);
        abort_unless($lesson->file_path, 404);
        // Legacy public-disk materials remain available only through this authorized endpoint.
        $disk = Storage::disk('local')->exists($lesson->file_path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($lesson->file_path), 404);

        return response()->file(Storage::disk($disk)->path($lesson->file_path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        $lesson->delete();

        return response()->json(status: 204);
    }
}
