<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('services.payment_provider.webhook_secret');
        $signature = $this->header('X-Payment-Signature');
        if (! $secret || ! $signature || ! hash_equals(hash_hmac('sha256', $this->getContent(), $secret), $signature)) {
            Log::warning('Payment webhook signature rejected.');
            throw new HttpResponseException(response()->json(['message' => 'Invalid webhook signature.'], 401));
        }

        return true;
    }

    public function rules(): array
    {
        return ['operation_id' => ['required', 'string', 'max:255'], 'status' => ['required', 'in:success,failed']];
    }
}
