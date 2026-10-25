<?php

namespace App\Contracts;

use App\Models\Purchase;

interface PaymentProviderInterface
{
    /**
     * @return array{operation_id: string, payment_url: string}
     */
    public function createPayment(Purchase $purchase): array;
}