<?php

namespace App\Http\Controllers;

use App\Events\CoursePurchased;
use App\Http\Requests\PaymentWebhookRequest;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data): JsonResponse {
            $purchase = Purchase::where(
                'provider_operation_id',
                $data['operation_id']
            )->lockForUpdate()->first();

            if (! $purchase) {
                return response()->json([
                    'message' => 'Purchase not found.',
                ], 404);
            }

            $nextStatus = $data['status'] === 'success' ? 'paid' : 'failed';
            if ($purchase->status !== 'pending') {
                if ($purchase->status !== $nextStatus) {
                    Log::warning('Payment webhook conflicts with terminal status.', ['operation_id' => $data['operation_id']]);

                    return response()->json(['message' => 'Payment result conflicts with an already processed operation.'], 409);
                }

                return response()->json([
                    'message' => 'Webhook already processed.',
                ]);
            }

            $purchase->status = $nextStatus;

            $purchase->save();
            Log::info('Payment webhook processed.', ['operation_id' => $data['operation_id'], 'purchase_id' => $purchase->id, 'status' => $nextStatus]);

            if ($purchase->status === 'paid') {
                CoursePurchased::dispatch($purchase->refresh());
            }

            return response()->json([
                'message' => 'Webhook processed.',
            ]);
        });
    }
}
