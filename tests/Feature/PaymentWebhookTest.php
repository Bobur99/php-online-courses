<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createPurchase(): Purchase
    {
        $student = User::factory()->create();
        $student->forceFill(['role' => 'student'])->save();

        $teacher = User::factory()->create();
        $teacher->forceFill(['role' => 'teacher'])->save();

        $category = Category::create([
            'name' => 'Backend',
        ]);

        $course = Course::create([
            'title' => 'Laravel Course',
            'description' => 'Course description',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);

        return Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => 100000,
            'status' => 'pending',
            'provider_operation_id' => 'operation-123',
        ]);
    }

    private function webhookPayload(): array
    {
        return [
            'operation_id' => 'operation-123',
            'status' => 'success',
        ];
    }

    private function signature(array $payload): string
    {
        return hash_hmac(
            'sha256',
            json_encode($payload),
            'test-secret'
        );
    }

    public function test_valid_successful_webhook_marks_purchase_as_paid(): void
    {
        config([
            'services.payment_provider.webhook_secret' => 'test-secret',
        ]);

        $purchase = $this->createPurchase();
        $payload = $this->webhookPayload();

        $this
            ->withHeader('X-Payment-Signature', $this->signature($payload))
            ->postJson('/api/webhooks/payment', $payload)
            ->assertOk();

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'paid',
        ]);
    }

    public function test_valid_failed_webhook_marks_purchase_as_failed(): void
    {
        config([
            'services.payment_provider.webhook_secret' => 'test-secret',
        ]);

        $purchase = $this->createPurchase();
        $payload = [
            'operation_id' => 'operation-123',
            'status' => 'failed',
        ];

        $this
            ->withHeader('X-Payment-Signature', $this->signature($payload))
            ->postJson('/api/webhooks/payment', $payload)
            ->assertOk();

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'failed',
        ]);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config([
            'services.payment_provider.webhook_secret' => 'test-secret',
        ]);

        $purchase = $this->createPurchase();
        $payload = $this->webhookPayload();

        $this
            ->withHeader('X-Payment-Signature', 'invalid-signature')
            ->postJson('/api/webhooks/payment', $payload)
            ->assertUnauthorized();

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'pending',
        ]);
    }

    public function test_repeated_successful_webhook_is_idempotent(): void
    {
        config([
            'services.payment_provider.webhook_secret' => 'test-secret',
        ]);

        $purchase = $this->createPurchase();
        $payload = $this->webhookPayload();
        $signature = $this->signature($payload);

        $this
            ->withHeader('X-Payment-Signature', $signature)
            ->postJson('/api/webhooks/payment', $payload)
            ->assertOk();

        $this
            ->withHeader('X-Payment-Signature', $signature)
            ->postJson('/api/webhooks/payment', $payload)
            ->assertOk();

        $this->assertDatabaseCount('purchases', 1);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => 'paid',
        ]);
    }

    public function test_unknown_status_and_missing_operation_are_rejected_without_changing_purchase(): void
    {
        config(['services.payment_provider.webhook_secret' => 'test-secret']);
        $purchase = $this->createPurchase();
        foreach ([['status' => 'success'], ['operation_id' => 'operation-123', 'status' => 'unknown']] as $payload) {
            $this->withHeader('X-Payment-Signature', $this->signature($payload))
                ->postJson('/api/webhooks/payment', $payload)->assertUnprocessable();
            $this->assertSame('pending', $purchase->refresh()->status);
        }
    }

    public function test_failed_operation_cannot_be_changed_to_success(): void
    {
        config(['services.payment_provider.webhook_secret' => 'test-secret']);
        $purchase = $this->createPurchase();
        $purchase->update(['status' => 'failed']);
        $payload = $this->webhookPayload();
        $this->withHeader('X-Payment-Signature', $this->signature($payload))
            ->postJson('/api/webhooks/payment', $payload)->assertConflict();
        $this->assertSame('failed', $purchase->refresh()->status);
    }
}
