<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Purchase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpPaymentProvider implements PaymentProviderInterface
{
    public function createPayment(Purchase $purchase): array
    {
        $response = Http::post(
            config('services.payment_provider.url').'/payments',
            [
                'amount' => $purchase->amount,
                'currency' => 'UZS',
                'description' => $purchase->course->title,
                'webhook_url' => config('services.payment_provider.webhook_url'),
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Payment provider request failed.');
        }

        $data = $response->json();

        if (
            ! is_array($data)
            || ! isset($data['operation_id'], $data['payment_url'])
        ) {
            throw new RuntimeException('Invalid payment provider response.');
        }

        return [
            'operation_id' => $data['operation_id'],
            'payment_url' => $data['payment_url'],
        ];
    }
}