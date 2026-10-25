<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class PurchaseService
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
    ) {}

    public function createPurchase(User $student, Course $course): array
    {
        if ($student->role !== 'student') {
            throw new AuthorizationException(
                'Only students can purchase courses.'
            );
        }

        if ($course->status !== 'published') {
            throw ValidationException::withMessages([
                'course' => 'Only published courses can be purchased.',
            ]);
        }

        if (Purchase::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'course' => 'Course has already been purchased.',
            ]);
        }

        $purchase = Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'pending',
        ]);

        $payment = $this->paymentProvider->createPayment($purchase);

        $purchase->update([
            'provider_operation_id' => $payment['operation_id'],
        ]);

        return [
            'purchase' => $purchase->refresh(),
            'payment_url' => $payment['payment_url'],
        ];
    }
}
