<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Events\CoursePublished;
use App\Events\CoursePurchased;
use App\Events\UserRegistered;
use Illuminate\Support\Facades\Log;

class LogDomainActivity
{
    public function handleRegistration(UserRegistered $event): void
    {
        Log::info('User registered.', ['user_id' => $event->user->id]);
    }

    public function handlePublication(CoursePublished $event): void
    {
        Log::info('Course published.', ['course_id' => $event->course->id]);
    }

    public function handlePurchase(CoursePurchased $event): void
    {
        Log::info('Course purchased.', ['purchase_id' => $event->purchase->id,
            'course_id' => $event->purchase->course_id, 'operation_id' => $event->purchase->provider_operation_id]);
    }

    public function handleCompletion(CourseCompleted $event): void
    {
        Log::info('Course completed.', ['student_id' => $event->student->id, 'course_id' => $event->course->id]);
    }
}
