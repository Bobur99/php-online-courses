<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Course;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;

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
}