<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_valid_payment_proof(): void
    {
        Storage::fake('local');

        $business = Business::create(['name' => 'Store D', 'slug' => 'store-d']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner D',
            'email' => 'owner@stored.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-5555',
            'customer_name' => 'Bob',
            'amount' => 75000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-555555',
            'expected_amount' => 75000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('transfer_receipt.jpg', 600, 800);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/proof", [
                'proof' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('payment.status', Payment::STATUS_PROOF_UPLOADED)
            ->assertJsonPath('proof.file_name', 'transfer_receipt.jpg');

        $this->assertDatabaseHas('payment_proofs', [
            'payment_id' => $payment->id,
            'business_id' => $business->id,
            'file_name' => 'transfer_receipt.jpg',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment_proof.uploaded',
        ]);
    }

    public function test_user_cannot_upload_invalid_file_type(): void
    {
        $business = Business::create(['name' => 'Store E', 'slug' => 'store-e']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner E',
            'email' => 'owner@storee.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-6666',
            'customer_name' => 'Charlie',
            'amount' => 100000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-666666',
            'expected_amount' => 100000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/proof", [
                'proof' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['proof']);
    }

    public function test_user_cannot_view_proof_from_another_tenant(): void
    {
        Storage::fake('local');

        $bizA = Business::create(['name' => 'Biz A', 'slug' => 'biz-a']);
        $userA = User::create([
            'business_id' => $bizA->id,
            'name' => 'User A',
            'email' => 'usera@test.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $bizB = Business::create(['name' => 'Biz B', 'slug' => 'biz-b']);
        $invoiceB = Invoice::create([
            'business_id' => $bizB->id,
            'invoice_number' => 'INV-20260902-7777',
            'customer_name' => 'David',
            'amount' => 200000,
            'status' => 'WAITING_PAYMENT',
        ]);
        $paymentB = Payment::create([
            'business_id' => $bizB->id,
            'invoice_id' => $invoiceB->id,
            'payment_number' => 'PAY-20260902-777777',
            'expected_amount' => 200000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        $tokenA = $userA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson("/api/payments/{$paymentB->id}/proof");

        $response->assertStatus(404);
    }
}
