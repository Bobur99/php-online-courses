<?php

namespace App\Jobs;

use App\Models\Purchase;
use App\Notifications\PaymentReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPaymentNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Purchase $purchase,
    ) {}

    public function handle(): void
    {
        $this->purchase->refresh();
        if ($this->purchase->status !== 'paid') {
            return;
        }
        $this->purchase->student->notify(new PaymentReceived($this->purchase));
        Log::info('Payment notification submitted to mail transport.', [
            'purchase_id' => $this->purchase->id,
            'student_id' => $this->purchase->student_id,
            'course_id' => $this->purchase->course_id,
        ]);
    }
}
