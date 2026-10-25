<?php

namespace App\Http\Controllers;

use App\Events\CourseCompleted;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Purchase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function complete(
        Request $request,
        Lesson $lesson
    ): JsonResponse {
        $student = $request->user();

        if ($student->role !== 'student') {
            throw new AuthorizationException(
                'Only students can complete lessons.'
            );
        }

        $hasAccess = Purchase::query()
            ->where('student_id', $student->id)
            ->where('course_id', $lesson->course_id)
            ->where('status', 'paid')
            ->exists();

        if (! $hasAccess) {
            throw new AuthorizationException(
                'Course must be purchased first.'
            );
        }

        $course = $lesson->course;

        $completedBefore = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereHas('lesson', function ($query) use ($course): void {
                $query->where('course_id', $course->id);
            })
            ->where('is_completed', true)
            ->count();

        $progress = LessonProgress::updateOrCreate(
            [
                'student_id' => $student->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_completed' => true,
                'completed_at' => now(),
            ]
        );

        $totalLessons = $course->lessons()->count();

        $completedLessons = LessonProgress::query()
            ->where('student_id', $student->id)
            ->whereHas('lesson', function ($query) use ($course): void {
                $query->where('course_id', $course->id);
            })
            ->where('is_completed', true)
            ->count();

        if (
            $totalLessons > 0
            && $completedLessons === $totalLessons
            && $completedBefore < $totalLessons
        ) {
            CourseCompleted::dispatch($student, $course);
        }

        return response()->json($progress);
    }
}