<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        [$purchase, $payment] = DB::transaction(function () use ($student, $course): array {
            // Lock a stable parent row: there may not be a purchase row yet.
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $purchases = Purchase::where('student_id', $student->id)->where('course_id', $course->id);
            if ((clone $purchases)->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages([
                    'course' => 'Course has already been purchased.',
                ]);
            }

            if ((clone $purchases)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'course' => 'There is already a payment awaiting confirmation.',
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
            Log::info('Purchase created.', ['purchase_id' => $purchase->id, 'operation_id' => $payment['operation_id']]);

            return [$purchase, $payment];
        });

        return [
            'purchase' => $purchase->refresh(),
            'payment_url' => $payment['payment_url'],
        ];
    }
}
