<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Purchase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpPaymentProvider implements PaymentProviderInterface
{
    public function createPayment(Purchase $purchase): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)->post(
                config('services.payment_provider.url').'/payments',
                [
                    'amount' => $purchase->amount,
                    'currency' => 'UZS',
                    'description' => $purchase->course->title,
                    'webhook_url' => config('services.payment_provider.webhook_url'),
                ]
            );
        } catch (ConnectionException $exception) {
            $this->unavailable($purchase, 'connection');
        }

        if (! $response->successful()) {
            $this->unavailable($purchase, 'http', $response->status());
        }

        $data = $response->json();

        if (
            ! is_array($data)
            || ! isset($data['operation_id'], $data['payment_url'])
            || ! is_string($data['operation_id']) || trim($data['operation_id']) === ''
            || ! is_string($data['payment_url']) || ! filter_var($data['payment_url'], FILTER_VALIDATE_URL)
            || ! in_array(parse_url($data['payment_url'], PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            $this->unavailable($purchase, 'invalid_response');
        }

        return [
            'operation_id' => $data['operation_id'],
            'payment_url' => $data['payment_url'],
        ];
    }

    private function unavailable(Purchase $purchase, string $reason, ?int $status = null): never
    {
        Log::warning('Payment provider unavailable.', ['purchase_id' => $purchase->id, 'reason' => $reason, 'http_status' => $status]);
        throw new HttpResponseException(response()->json(['message' => 'Payment provider request failed.'], 503));
    }
}
