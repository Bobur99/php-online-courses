<?php

namespace App\Listeners;

use App\Events\CoursePurchased;
use App\Jobs\SendPaymentNotification;

class QueuePaymentNotification
{
    public function handle(CoursePurchased $event): void
    {
        SendPaymentNotification::dispatch($event->purchase);
    }
}