<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

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

        $lastPurchase = Purchase::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->latest('id')
            ->first();

        if ($lastPurchase?->status === 'paid') {
            throw ValidationException::withMessages([
                'course' => 'Course has already been purchased.',
            ]);
        }

        if ($lastPurchase?->status === 'pending') {
            throw ValidationException::withMessages([
                'course' => 'There is already a payment awaiting confirmation.',
            ]);
        }

        [$purchase, $payment] = DB::transaction(function () use ($student, $course): array {
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

            return [$purchase, $payment];
        });

        return [
            'purchase' => $purchase->refresh(),
            'payment_url' => $payment['payment_url'],
        ];
    }
}
