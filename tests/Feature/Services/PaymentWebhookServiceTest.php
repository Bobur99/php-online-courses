<?php

namespace Tests\Feature\Services;

use App\Events\CoursePurchased;
use App\Models\Category;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PaymentWebhookService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(): Purchase
    {
        $course = Course::create(['title' => 'Laravel', 'description' => 'Описание', 'price' => 100,
            'category_id' => Category::create(['name' => 'Backend'])->id,
            'author_id' => User::factory()->create(['role' => 'teacher'])->id, 'status' => 'published']);

        return Purchase::create(['student_id' => User::factory()->create(['role' => 'student'])->id,
            'course_id' => $course->id, 'amount' => 100, 'status' => 'pending',
            'provider_operation_id' => 'operation-service-test']);
    }

    public function test_success_is_applied_once_and_repeated_notification_has_no_side_effects(): void
    {
        Event::fake([CoursePurchased::class]);
        $purchase = $this->purchase();
        $service = app(PaymentWebhookService::class);

        $this->assertTrue($service->process($purchase->provider_operation_id, 'success'));
        $this->assertFalse($service->process($purchase->provider_operation_id, 'success'));
        $this->assertSame('paid', $purchase->refresh()->status);
        Event::assertDispatchedTimes(CoursePurchased::class, 1);
    }

    public function test_conflicting_result_preserves_terminal_status_and_emits_no_event(): void
    {
        Event::fake([CoursePurchased::class]);
        $purchase = $this->purchase();
        $service = app(PaymentWebhookService::class);
        $this->assertTrue($service->process($purchase->provider_operation_id, 'failed'));

        try {
            $service->process($purchase->provider_operation_id, 'success');
            $this->fail('A conflicting payment result must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Payment result conflicts with an already processed operation.', $exception->getMessage());
        }

        $this->assertSame('failed', $purchase->refresh()->status);
        Event::assertNotDispatched(CoursePurchased::class);
    }

    public function test_unknown_operation_does_not_create_a_purchase(): void
    {
        $service = app(PaymentWebhookService::class);

        try {
            $service->process('missing-operation', 'success');
            $this->fail('An unknown operation must be rejected.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Purchase::class, $exception->getModel());
        }

        $this->assertDatabaseCount('purchases', 0);
    }
}
