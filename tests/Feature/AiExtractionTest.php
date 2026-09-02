<?php

namespace Tests\Feature;

use App\Domain\AI\Providers\MockAiVisionProvider;
use App\Jobs\ProcessPaymentProofAiJob;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_job_extracts_structured_data_and_updates_payment_status(): void
    {
        Storage::fake('local');

        $business = Business::create(['name' => 'Store AI', 'slug' => 'store-ai']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-8888',
            'customer_name' => 'Eve',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-888888',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_PROOF_UPLOADED,
        ]);

        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/test_proof.jpg',
            'file_name' => 'receipt.jpg',
            'file_hash' => 'dummyhash123',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);

        // Create dummy file on fake storage disk
        Storage::put('private/payment_proofs/test_proof.jpg', 'dummy image content');

        $mockProvider = new MockAiVisionProvider(mockAmount: 125000.00, confidenceScore: 0.97);

        // Process AI Extraction job directly with mock provider
        $job = new ProcessPaymentProofAiJob($proof, $mockProvider);
        $job->handle();

        $payment->refresh();
        $this->assertEquals(Payment::STATUS_WAITING_VERIFICATION, $payment->status);

        $this->assertDatabaseHas('payment_extractions', [
            'payment_proof_id' => $proof->id,
            'extracted_amount' => 125000.00,
            'confidence_score' => 0.97,
            'status' => 'COMPLETED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai.extraction_completed',
        ]);
    }

    public function test_ai_job_handles_failed_extraction_gracefully(): void
    {
        Storage::fake('local');

        $business = Business::create(['name' => 'Store AI Fail', 'slug' => 'store-ai-fail']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-9999',
            'customer_name' => 'Frank',
            'amount' => 50000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-999999',
            'expected_amount' => 50000,
            'status' => Payment::STATUS_PROOF_UPLOADED,
        ]);

        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/bad_proof.jpg',
            'file_name' => 'corrupted.jpg',
            'file_hash' => 'badhash123',
            'file_size' => 512,
            'mime_type' => 'image/jpeg',
        ]);

        Storage::put('private/payment_proofs/bad_proof.jpg', 'corrupted content');

        $failingProvider = new MockAiVisionProvider(shouldFail: true);

        $job = new ProcessPaymentProofAiJob($proof, $failingProvider);
        $job->handle();

        $payment->refresh();
        $this->assertEquals(Payment::STATUS_AI_PROCESSING_FAILED, $payment->status);

        $this->assertDatabaseHas('payment_extractions', [
            'payment_proof_id' => $proof->id,
            'status' => 'FAILED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai.extraction_failed',
        ]);
    }
}
