<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class LessonService
{
    public function forCourse(Course $course, ?User $user): Collection
    {
        $completedLessonIds = $user?->can(PermissionName::StudyCourses->value)
            ? LessonProgress::query()
                ->where('student_id', $user->id)
                ->where('is_completed', true)
                ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
                ->pluck('lesson_id')
                ->all()
            : [];
        $canAccessMaterials = $user !== null && Gate::forUser($user)->allows('viewContent', $course);
        abort_unless($course->status === 'published' || $canAccessMaterials, 404);

        return $course->lessons()
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
            });
    }

    public function create(Course $course, array $data, ?UploadedFile $file = null): Lesson
    {
        $data['file_path'] = $file?->store('lessons', 'local');

        return $course->lessons()->create($data);
    }

    public function update(Lesson $lesson, array $data, ?UploadedFile $file = null): Lesson
    {
        if ($file) {
            $data['file_path'] = $file->store('lessons', 'local');
        }
        $lesson->update($data);

        return $lesson;
    }

    public function materialPath(Lesson $lesson): string
    {
        abort_unless($lesson->file_path, 404);
        // Legacy public-disk materials are served only through the authorized API.
        $disk = Storage::disk('local')->exists($lesson->file_path) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($lesson->file_path), 404);

        return Storage::disk($disk)->path($lesson->file_path);
    }

    public function delete(Lesson $lesson): void
    {
        $lesson->delete();
    }
}
