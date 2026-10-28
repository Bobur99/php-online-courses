<?php

namespace App\Services;

use App\Events\CoursePurchased;
use App\Models\Purchase;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    /** Returns false when the same result has already been processed. */
    public function process(string $operationId, string $status): bool
    {
        return DB::transaction(function () use ($operationId, $status): bool {
            $purchase = Purchase::where('provider_operation_id', $operationId)
                ->lockForUpdate()
                ->firstOrFail();

            $nextStatus = $status === 'success' ? 'paid' : 'failed';
            if ($purchase->status !== 'pending') {
                if ($purchase->status !== $nextStatus) {
                    Log::warning('Payment webhook conflicts with terminal status.', ['operation_id' => $operationId]);

                    throw new DomainException('Payment result conflicts with an already processed operation.');
                }

                return false;
            }

            $purchase->status = $nextStatus;
            $purchase->save();
            Log::info('Payment webhook processed.', ['operation_id' => $operationId,
                'purchase_id' => $purchase->id, 'status' => $nextStatus]);

            if ($purchase->status === 'paid') {
                CoursePurchased::dispatch($purchase->refresh());
            }

            return true;
        });
    }
}
