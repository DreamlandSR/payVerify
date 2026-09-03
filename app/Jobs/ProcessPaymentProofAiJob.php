<?php

namespace App\Jobs;

use App\Domain\AI\Contracts\AIExtractionProviderInterface;
use App\Domain\AI\Services\AIExtractionService;
use App\Domain\Validation\Services\PaymentValidationService;
use App\Domain\Validation\Services\RiskAnalysisService;
use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentProof;
use App\Services\AuditLoggerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessPaymentProofAiJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PaymentProof $proof,
        public ?AIExtractionProviderInterface $customProvider = null
    ) {}

    public function handle(): void
    {
        $payment = $this->proof->payment;

        // Set status to AI_PROCESSING
        $payment->update([
            'status' => Payment::STATUS_AI_PROCESSING,
        ]);

        $absolutePath = Storage::disk('local')->path($this->proof->file_path);
        if (! file_exists($absolutePath)) {
            $absolutePath = storage_path('app/'.$this->proof->file_path);
        }
        if (! file_exists($absolutePath)) {
            $absolutePath = storage_path('app/private/'.$this->proof->file_path);
        }
        if (! file_exists($absolutePath) && Str::startsWith($this->proof->file_path, 'private/')) {
            $trimmed = Str::after($this->proof->file_path, 'private/');
            $absolutePath = storage_path('app/private/'.$trimmed);
        }

        $aiService = new AIExtractionService($this->customProvider);
        $result = $aiService->extractFromProof($absolutePath, $this->proof->file_name);

        // Delete existing extraction if any
        PaymentExtraction::where('payment_proof_id', $this->proof->id)->delete();

        $extraction = PaymentExtraction::create([
            'business_id' => $this->proof->business_id,
            'payment_proof_id' => $this->proof->id,
            'raw_ocr_text' => $result->rawOcrText,
            'extracted_amount' => $result->amount,
            'extracted_currency' => $result->currency,
            'extracted_date' => $result->date,
            'extracted_time' => $result->time,
            'extracted_provider' => $result->provider,
            'extracted_ref_number' => $result->referenceNumber,
            'extracted_merchant_name' => $result->merchantName,
            'confidence_score' => $result->confidenceScore,
            'status' => $result->success ? 'COMPLETED' : 'FAILED',
            'error_message' => $result->errorMessage,
        ]);

        if ($result->success) {
            // Run validation engine
            $validationService = new PaymentValidationService;
            $validationService->validate($payment, $extraction);

            // Run risk analysis engine
            $riskAnalysisService = new RiskAnalysisService;
            $riskAnalysisService->assess($payment, $extraction);

            $payment->update([
                'status' => Payment::STATUS_WAITING_VERIFICATION,
            ]);

            AuditLoggerService::log(
                action: 'ai.extraction_completed',
                resourceType: PaymentExtraction::class,
                resourceId: (string) $extraction->id,
                metadata: [
                    'payment_number' => $payment->payment_number,
                    'extracted_amount' => $result->amount,
                    'confidence_score' => $result->confidenceScore,
                ]
            );
        } else {
            $payment->update([
                'status' => Payment::STATUS_AI_PROCESSING_FAILED,
            ]);

            AuditLoggerService::log(
                action: 'ai.extraction_failed',
                resourceType: PaymentExtraction::class,
                resourceId: (string) $extraction->id,
                metadata: [
                    'payment_number' => $payment->payment_number,
                    'error' => $result->errorMessage,
                ]
            );
        }
    }
}
