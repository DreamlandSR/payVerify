<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_public_payment_details_without_authentication(): void
    {
        $business = Business::create(['name' => 'Pub Store', 'slug' => 'pub-store']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-PUB-001',
            'customer_name' => 'Customer Public',
            'amount' => 175000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-PUB-001',
            'expected_amount' => 175000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        $response = $this->getJson("/api/public/payments/{$payment->payment_number}");

        $response->assertStatus(200)
            ->assertJsonPath('payment.payment_number', 'PAY-PUB-001')
            ->assertJsonPath('payment.customer_name', 'Customer Public')
            ->assertJsonPath('payment.expected_amount', 175000);
    }

    public function test_customer_can_upload_payment_proof_screenshot_without_authentication(): void
    {
        Storage::fake('local');

        $business = Business::create(['name' => 'Pub Upload Store', 'slug' => 'pub-up-store']);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-PUB-002',
            'customer_name' => 'Customer Proof Upload',
            'amount' => 250000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-PUB-002',
            'expected_amount' => 250000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg', 600, 800);

        $response = $this->postJson("/api/public/payments/{$payment->payment_number}/proof", [
            'proof_image' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertContains($response->json('status'), [Payment::STATUS_WAITING_VERIFICATION, Payment::STATUS_PROOF_UPLOADED, Payment::STATUS_AI_PROCESSING_FAILED]);

        $this->assertDatabaseHas('payment_proofs', [
            'payment_id' => $payment->id,
            'file_name' => 'receipt.jpg',
        ]);

        $this->assertContains($payment->fresh()->status, [Payment::STATUS_WAITING_VERIFICATION, Payment::STATUS_PROOF_UPLOADED, Payment::STATUS_AI_PROCESSING_FAILED]);
    }
}
