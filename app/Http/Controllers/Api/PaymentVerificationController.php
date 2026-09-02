<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Payment;
use App\Models\PaymentVerification;
use App\Models\Subscription;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;

class PaymentVerificationController extends Controller
{
    /**
     * Submit human verification decision (VALID or INVALID) for a payment.
     */
    public function verify(VerifyPaymentRequest $request, string $paymentId): JsonResponse
    {
        $payment = Payment::with('invoice')->findOrFail($paymentId);

        // Check if payment is already finalized
        if (in_array($payment->status, [Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED])) {
            return response()->json([
                'message' => 'Payment has already been finalized as '.$payment->status.'.',
            ], 422);
        }

        $user = $request->user();
        $validated = $request->validated();
        $decision = $validated['decision'];

        $verifiedAt = now();

        if ($decision === 'VALID') {
            $payment->update([
                'status' => Payment::STATUS_VERIFIED,
                'verified_at' => $verifiedAt,
                'verified_by_user_id' => $user->id,
            ]);

            if ($payment->invoice) {
                $payment->invoice->update([
                    'status' => 'PAID',
                ]);
            }

            // Increment usage count for current month subscription
            Subscription::where('business_id', $payment->business_id)->increment('current_month_usage');

            AuditLoggerService::log(
                action: 'payment.verified',
                resourceType: Payment::class,
                resourceId: (string) $payment->id,
                metadata: [
                    'payment_number' => $payment->payment_number,
                    'verified_by' => $user->name,
                    'notes' => $validated['verification_notes'] ?? null,
                ]
            );
        } else {
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'verified_at' => $verifiedAt,
                'verified_by_user_id' => $user->id,
            ]);

            AuditLoggerService::log(
                action: 'payment.rejected',
                resourceType: Payment::class,
                resourceId: (string) $payment->id,
                metadata: [
                    'payment_number' => $payment->payment_number,
                    'rejected_by' => $user->name,
                    'rejection_reason' => $validated['rejection_reason'],
                    'notes' => $validated['verification_notes'] ?? null,
                ]
            );
        }

        // Save verification record
        $verification = PaymentVerification::create([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'decision' => $decision,
            'rejection_reason' => $decision === 'INVALID' ? $validated['rejection_reason'] : null,
            'verification_notes' => $validated['verification_notes'] ?? null,
            'verified_at' => $verifiedAt,
        ]);

        return response()->json([
            'message' => 'Payment verification decision submitted successfully.',
            'decision' => $decision,
            'verification' => $verification->load('verifier'),
            'payment' => $payment->fresh(['invoice', 'verifier']),
        ]);
    }
}
