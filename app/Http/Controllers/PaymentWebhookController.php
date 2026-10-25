<?php

namespace App\Http\Controllers;

use App\Events\CoursePurchased;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Payment-Signature');
        $payload = $request->getContent();

        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            config('services.payment_provider.webhook_secret')
        );

        if (
            ! $signature
            || ! hash_equals($expectedSignature, $signature)
        ) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $data = $request->json()->all();

        $purchase = Purchase::where(
            'provider_operation_id',
            $data['operation_id'] ?? null
        )->first();

        if (! $purchase) {
            return response()->json([
                'message' => 'Purchase not found.',
            ], 404);
        }

        if ($purchase->status === 'paid') {
            return response()->json([
                'message' => 'Webhook already processed.',
            ]);
        }

        $purchase->status = ($data['status'] ?? null) === 'success'
            ? 'paid'
            : 'failed';

        $purchase->save();

        if ($purchase->status === 'paid') {
            CoursePurchased::dispatch($purchase->refresh());
        }

        return response()->json([
            'message' => 'Webhook processed.',
        ]);
    }
}