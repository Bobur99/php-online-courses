<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Requests\CourseRequest;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    public function adminIndex(): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        return response()->json(['data' => $this->courseService->forAdministration()]);
    }

    public function teacherIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(PermissionName::ViewOwnCourses->value), 403);

        return response()->json(['data' => $this->courseService->forTeacher($request->user())]);
    }

    public function studentIndex(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(PermissionName::StudyCourses->value), 403);

        return response()->json(['data' => $this->courseService->forStudent($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->courseService->catalog([
                'search' => $request->filled('search') ? $request->string('search')->toString() : null,
                'category_id' => $request->filled('category_id') ? $request->integer('category_id') : null,
                'teacher_id' => $request->filled('teacher_id') ? $request->integer('teacher_id') : null,
                'sort' => $request->query('sort', 'created_at'),
                'direction' => $request->query('direction', 'desc'),
            ]),
        ]);
    }

    public function show(Course $course): JsonResponse
    {
        return response()->json($this->courseService->details($course, auth('sanctum')->user()));
    }

    public function store(CourseRequest $request): JsonResponse
    {
        $this->authorize('create', Course::class);

        return response()->json($this->courseService->create($request->user(), $request->validated()), 201);
    }

    public function update(CourseRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        return response()->json($this->courseService->update($course, $request->validated()));
    }

    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        $this->courseService->delete($course);

        return response()->json(status: 204);
    }

    public function publish(Course $course): JsonResponse
    {
        $this->authorize('publish', $course);

        return response()->json($this->courseService->publish($course));
    }

    public function unpublish(Course $course): JsonResponse
    {
        $this->authorize('publish', $course);

        return response()->json($this->courseService->unpublish($course));
    }
}
