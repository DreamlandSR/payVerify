<?php

namespace App\Http\Controllers\Api;

use App\Domain\Validation\Services\PaymentValidationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadProofRequest;
use App\Jobs\ProcessPaymentProofAiJob;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PublicPaymentController extends Controller
{
    /**
     * Get public payment details for customer portal.
     */
    public function show(string $paymentNumber): JsonResponse
    {
        $payment = Payment::withoutGlobalScopes()
            ->with(['invoice', 'proof'])
            ->where('payment_number', $paymentNumber)
            ->firstOrFail();

        return response()->json([
            'payment' => [
                'payment_number' => $payment->payment_number,
                'invoice_number' => $payment->invoice?->invoice_number,
                'customer_name' => $payment->invoice?->customer_name,
                'expected_amount' => (float) $payment->expected_amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'qr_code_url' => $payment->qr_code_url,
                'expires_at' => $payment->expires_at?->toIso8601String(),
                'has_proof' => $payment->proof !== null,
                'verified_at' => $payment->verified_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Upload payment proof screenshot/photo from customer portal.
     */
    public function uploadProof(UploadProofRequest $request, string $paymentNumber): JsonResponse
    {
        $payment = Payment::withoutGlobalScopes()
            ->where('payment_number', $paymentNumber)
            ->firstOrFail();

        if (in_array($payment->status, [Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED])) {
            return response()->json([
                'message' => 'Payment has already been finalized as '.$payment->status.'.',
            ], 422);
        }

        $file = $request->file('proof') ?? $request->file('proof_image');
        $extension = $file->getClientOriginalExtension();
        $fileName = (string) Str::uuid().'.'.$extension;
        $fileHash = hash_file('sha256', $file->getRealPath());
        $filePath = $file->storeAs('payment_proofs', $fileName);

        // Delete existing proof if re-uploading
        PaymentProof::withoutGlobalScopes()
            ->where('payment_id', $payment->id)
            ->delete();

        $proof = PaymentProof::create([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_hash' => $fileHash,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        $payment->update([
            'status' => Payment::STATUS_PROOF_UPLOADED,
        ]);

        // Dispatch AI extraction job synchronously for instant detection
        ProcessPaymentProofAiJob::dispatchSync($proof);

        $payment->refresh();
        $proof->load(['extraction', 'validationResult']);
        $extraction = $proof->extraction;

        // Open Donation Logic: If AI OCR extracted a nominal > 0, adjust payment & invoice amount to the OCR detected nominal
        if ($extraction && $extraction->extracted_amount && $extraction->extracted_amount > 0 && $extraction->status === 'COMPLETED') {
            $payment->update([
                'expected_amount' => $extraction->extracted_amount,
                'status' => Payment::STATUS_WAITING_VERIFICATION,
            ]);

            if ($payment->invoice) {
                $payment->invoice->update([
                    'amount' => $extraction->extracted_amount,
                ]);
            }

            // Re-run validation so amount matches exact OCR detection
            $validationService = new PaymentValidationService;
            $validationService->validate($payment, $extraction);

            $proof->load('validationResult');
            $validation = $proof->validationResult;
            $isAmountMatched = true;
            $isValidOcr = true;
        } else {
            $validation = $proof->validationResult;
            $isAmountMatched = false;
            $isValidOcr = false;
        }

        AuditLoggerService::log(
            action: 'payment_proof.uploaded_by_customer',
            resourceType: PaymentProof::class,
            resourceId: (string) $proof->id,
            metadata: [
                'payment_number' => $payment->payment_number,
                'file_name' => $proof->file_name,
                'file_size' => $proof->file_size,
                'extracted_amount' => $extraction?->extracted_amount,
                'is_valid' => $isValidOcr,
            ]
        );

        return response()->json([
            'message' => $isValidOcr
                ? 'Bukti transfer berhasil diunggah! AI OCR berhasil mendeteksi nominal donasi.'
                : 'Foto struk tidak valid. AI tidak menemukan angka nominal transfer pada gambar.',
            'status' => $payment->status,
            'proof' => [
                'id' => $proof->id,
                'file_name' => $proof->file_name,
            ],
            'ai_analysis' => [
                'is_valid' => $isValidOcr,
                'is_amount_matched' => $isAmountMatched,
                'extracted_amount' => $extraction?->extracted_amount ? (float) $extraction->extracted_amount : null,
                'expected_amount' => (float) $payment->expected_amount,
                'extracted_provider' => $extraction?->extracted_provider,
                'extracted_date' => $extraction?->extracted_date?->toDateString(),
                'confidence_score' => $extraction?->confidence_score,
                'ocr_status' => $extraction?->status,
                'error_message' => $isValidOcr ? null : ($extraction?->error_message ?? '❌ FOTO STRUK TIDAK VALID / TIDAK TERBACA: AI tidak dapat menemukan angka nominal transfer pada foto yang Anda unggah. Silakan pastikan foto struk transfer terang, jelas, dan unggah ulang.'),
            ],
        ], 201);
    }
}
