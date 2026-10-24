<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseRequest;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Course::query()
                ->where('status', 'published')
                ->get(),
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

        return response()->json($course, 201);
    }

    public function update(
        CourseRequest $request,
        Course $course
    ): JsonResponse {
        $this->authorize('update', $course);

        $course->update($request->validated());

        return response()->json($course);
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

        return response()->json($course);
    }
}
