<?php

namespace App\Jobs;

use App\Models\Purchase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPaymentNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Purchase $purchase,
    ) {
    }

    public function handle(): void
    {
        Log::info('Payment notification sent.', [
            'purchase_id' => $this->purchase->id,
            'student_id' => $this->purchase->student_id,
            'course_id' => $this->purchase->course_id,
        ]);
    }
}