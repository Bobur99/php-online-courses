<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentWebhookRequest;
use App\Services\PaymentWebhookService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentWebhookService $webhookService) {}

    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $processed = $this->webhookService->process($data['operation_id'], $data['status']);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Purchase not found.'], 404);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => $processed ? 'Webhook processed.' : 'Webhook already processed.',
        ]);
    }
}
