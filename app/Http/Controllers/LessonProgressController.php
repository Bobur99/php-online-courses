<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Services\LessonProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function __construct(private LessonProgressService $progressService) {}

    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        return response()->json($this->progressService->complete($request->user(), $lesson));
    }
}
