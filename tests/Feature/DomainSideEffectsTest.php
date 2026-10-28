<?php

namespace Tests\Feature;

use App\Events\CourseCompleted;
use App\Events\CoursePublished;
use App\Events\CoursePurchased;
use App\Events\UserRegistered;
use App\Jobs\SendPaymentNotification;
use App\Models\Category;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(): Purchase
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => 'Side effects']);
        $course = Course::create(['title' => 'Курс', 'description' => 'Описание', 'price' => 100,
            'author_id' => $teacher->id, 'category_id' => $category->id, 'status' => 'published']);

        return Purchase::create(['student_id' => $student->id, 'course_id' => $course->id,
            'amount' => 100, 'status' => 'paid', 'provider_operation_id' => 'operation-side-effects']);
    }

    public function test_required_domain_events_are_logged(): void
    {
        Queue::fake();
        Log::spy();
        $purchase = $this->purchase();
        UserRegistered::dispatch($purchase->student);
        CoursePublished::dispatch($purchase->course);
        CoursePurchased::dispatch($purchase);
        CourseCompleted::dispatch($purchase->student, $purchase->course);
        foreach (['User registered.', 'Course published.', 'Course purchased.', 'Course completed.'] as $message) {
            Log::shouldHaveReceived('info')->with($message, \Mockery::type('array'))->once();
        }
    }

    public function test_payment_job_sends_a_real_notification_only_for_paid_purchase(): void
    {
        Notification::fake();
        $purchase = $this->purchase();
        (new SendPaymentNotification($purchase))->handle();
        Notification::assertSentTo($purchase->student, PaymentReceived::class);
        $purchase->update(['status' => 'failed']);
        Notification::fake();
        (new SendPaymentNotification($purchase))->handle();
        Notification::assertNothingSent();
    }
}
