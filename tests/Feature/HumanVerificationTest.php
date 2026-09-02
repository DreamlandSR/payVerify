<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_verify_payment_as_valid(): void
    {
        $business = Business::create(['name' => 'Store V', 'slug' => 'store-v']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Admin V',
            'email' => 'admin@storev.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 0,
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-1111',
            'customer_name' => 'Grace',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-111111',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/verify", [
                'decision' => 'VALID',
                'verification_notes' => 'Nominal dan bukti transfer sesuai.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('decision', 'VALID')
            ->assertJsonPath('payment.status', Payment::STATUS_VERIFIED)
            ->assertJsonPath('payment.invoice.status', 'PAID');

        $this->assertDatabaseHas('payment_verifications', [
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'decision' => 'VALID',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $business->id,
            'current_month_usage' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.verified',
        ]);
    }

    public function test_authorized_user_can_reject_payment_as_invalid_with_reason(): void
    {
        $business = Business::create(['name' => 'Store R', 'slug' => 'store-r']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Admin R',
            'email' => 'admin@storer.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-2222',
            'customer_name' => 'Hank',
            'amount' => 200000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-222222',
            'expected_amount' => 200000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/verify", [
                'decision' => 'INVALID',
                'rejection_reason' => 'Nominal transfer kurang Rp50.000.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('decision', 'INVALID')
            ->assertJsonPath('payment.status', Payment::STATUS_REJECTED);

        $this->assertDatabaseHas('payment_verifications', [
            'payment_id' => $payment->id,
            'decision' => 'INVALID',
            'rejection_reason' => 'Nominal transfer kurang Rp50.000.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.rejected',
        ]);
    }

    public function test_rejection_fails_without_rejection_reason(): void
    {
        $business = Business::create(['name' => 'Store F', 'slug' => 'store-f']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Admin F',
            'email' => 'admin@storef.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-3333',
            'customer_name' => 'Ian',
            'amount' => 100000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-333333',
            'expected_amount' => 100000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/verify", [
                'decision' => 'INVALID',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_user_cannot_reverify_already_finalized_payment(): void
    {
        $business = Business::create(['name' => 'Store Fin', 'slug' => 'store-fin']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Admin Fin',
            'email' => 'admin@storefin.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-4444',
            'customer_name' => 'Jack',
            'amount' => 50000,
            'status' => 'PAID',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260902-444444',
            'expected_amount' => 50000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/verify", [
                'decision' => 'INVALID',
                'rejection_reason' => 'Already verified before',
            ]);

        $response->assertStatus(422);
    }
}
