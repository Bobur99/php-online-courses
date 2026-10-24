<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseRequest;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Course::query()
            ->where('status', 'published');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where('title', 'ilike', '%' . $search . '%');
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
                ->orderBy($sort, $direction)
                ->orderBy('id', $direction)
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
