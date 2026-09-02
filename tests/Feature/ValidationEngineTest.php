<?php

namespace Tests\Feature;

use App\Domain\Validation\Services\PaymentValidationService;
use App\Domain\Validation\Services\RiskAnalysisService;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_service_detects_matching_amount(): void
    {
        $business = Business::create(['name' => 'Test Biz', 'slug' => 'test-biz']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-001',
            'customer_name' => 'Alice',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-001',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);
        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/proof1.jpg',
            'file_name' => 'proof1.jpg',
            'file_hash' => 'hash111',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);
        $extraction = PaymentExtraction::create([
            'business_id' => $business->id,
            'payment_proof_id' => $proof->id,
            'extracted_amount' => 125000,
            'extracted_currency' => 'IDR',
            'extracted_date' => date('Y-m-d'),
            'confidence_score' => 0.95,
            'status' => 'COMPLETED',
        ]);

        $service = new PaymentValidationService;
        $result = $service->validate($payment, $extraction);

        $this->assertTrue($result->is_amount_matched);
        $this->assertTrue($result->is_currency_matched);
        $this->assertNull($result->discrepancy_details);
    }

    public function test_validation_service_detects_mismatching_amount(): void
    {
        $business = Business::create(['name' => 'Test Biz 2', 'slug' => 'test-biz-2']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-002',
            'customer_name' => 'Bob',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-002',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);
        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/proof2.jpg',
            'file_name' => 'proof2.jpg',
            'file_hash' => 'hash222',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);
        $extraction = PaymentExtraction::create([
            'business_id' => $business->id,
            'payment_proof_id' => $proof->id,
            'extracted_amount' => 100000, // Mismatch!
            'extracted_currency' => 'IDR',
            'confidence_score' => 0.95,
            'status' => 'COMPLETED',
        ]);

        $service = new PaymentValidationService;
        $result = $service->validate($payment, $extraction);

        $this->assertFalse($result->is_amount_matched);
        $this->assertNotNull($result->discrepancy_details);
    }

    public function test_risk_analysis_assigns_high_risk_for_amount_mismatch(): void
    {
        $business = Business::create(['name' => 'Risk Biz', 'slug' => 'risk-biz']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-003',
            'customer_name' => 'Charlie',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-003',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);
        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/proof3.jpg',
            'file_name' => 'proof3.jpg',
            'file_hash' => 'hash333',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);
        $extraction = PaymentExtraction::create([
            'business_id' => $business->id,
            'payment_proof_id' => $proof->id,
            'extracted_amount' => 100000, // Amount mismatch => HIGH risk
            'confidence_score' => 0.60, // Low confidence => MEDIUM risk
            'status' => 'COMPLETED',
        ]);

        $riskService = new RiskAnalysisService;
        $assessment = $riskService->assess($payment, $extraction);

        $this->assertEquals('HIGH', $assessment->risk_level);
        $this->assertCount(3, $assessment->risk_factors); // amount_mismatch, low_confidence, missing_info
    }

    public function test_analysis_endpoint_returns_full_findings(): void
    {
        $business = Business::create(['name' => 'Analysis Biz', 'slug' => 'analysis-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Admin User',
            'email' => 'admin@analysis.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-004',
            'customer_name' => 'David',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-004',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/payments/{$payment->id}/analysis");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment',
                'expected' => ['amount', 'currency'],
                'extraction',
                'validation',
                'risk',
            ]);
    }
}
