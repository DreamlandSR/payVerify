<?php

namespace Tests\Feature;

use App\Domain\Reconciliation\Services\ReconciliationService;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentProof;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_service_detects_all_match_when_all_3_sources_agree(): void
    {
        $business = Business::create(['name' => 'Rec Biz 1', 'slug' => 'rec-biz-1']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-REC-001',
            'customer_name' => 'Alice',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-REC-001',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);
        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/proof_rec1.jpg',
            'file_name' => 'proof1.jpg',
            'file_hash' => 'hashrec1',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);
        PaymentExtraction::create([
            'business_id' => $business->id,
            'payment_proof_id' => $proof->id,
            'extracted_amount' => 125000,
            'extracted_currency' => 'IDR',
            'confidence_score' => 0.95,
            'status' => 'COMPLETED',
        ]);

        WebhookEvent::create([
            'business_id' => $business->id,
            'provider' => 'mock_qris',
            'event_type' => 'payment.succeeded',
            'payload' => [
                'payment_number' => 'PAY-REC-001',
                'status' => 'PAID',
                'amount' => 125000,
            ],
            'status' => 'PROCESSED',
            'processed_at' => now(),
        ]);

        $service = new ReconciliationService;
        $report = $service->reconcile($payment);

        $this->assertEquals('ALL_MATCH', $report['reconciliation_status']);
        $this->assertNotNull($report['sources']['proof']);
        $this->assertNotNull($report['sources']['provider']);
    }

    public function test_reconciliation_service_detects_provider_data_not_found(): void
    {
        $business = Business::create(['name' => 'Rec Biz 2', 'slug' => 'rec-biz-2']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-REC-002',
            'customer_name' => 'Bob',
            'amount' => 200000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-REC-002',
            'expected_amount' => 200000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);
        $proof = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
            'file_path' => 'private/payment_proofs/proof_rec2.jpg',
            'file_name' => 'proof2.jpg',
            'file_hash' => 'hashrec2',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
        ]);
        PaymentExtraction::create([
            'business_id' => $business->id,
            'payment_proof_id' => $proof->id,
            'extracted_amount' => 200000,
            'confidence_score' => 0.95,
            'status' => 'COMPLETED',
        ]);

        // No webhook received!

        $service = new ReconciliationService;
        $report = $service->reconcile($payment);

        $this->assertEquals('PROVIDER_DATA_NOT_FOUND', $report['reconciliation_status']);
        $this->assertNull($report['sources']['provider']);
    }

    public function test_reconciliation_endpoint_returns_json_report(): void
    {
        $business = Business::create(['name' => 'Rec Biz 3', 'slug' => 'rec-biz-3']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Rec Owner',
            'email' => 'owner@rec.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-REC-003',
            'customer_name' => 'Charlie',
            'amount' => 50000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-REC-003',
            'expected_amount' => 50000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/payments/{$payment->id}/reconciliation");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment_id',
                'payment_number',
                'reconciliation_status',
                'summary',
                'sources' => ['expected', 'proof', 'provider'],
            ]);
    }
}
