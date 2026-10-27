<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Course;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchaseService,
    ) {
    }

    public function store(
        PurchaseRequest $request,
        Course $course
    ): JsonResponse {
        $result = $this->purchaseService->createPurchase(
            $request->user(),
            $course,
        );

        return response()->json([
            'purchase' => $result['purchase'],
            'payment_url' => $result['payment_url'],
        ], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $purchase = Purchase::query()
            ->where('student_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->latest('id')
            ->first();

        return response()->json([
            'purchase' => $purchase,
            'is_purchased' => $purchase?->status === 'paid',
        ]);
    }
}
