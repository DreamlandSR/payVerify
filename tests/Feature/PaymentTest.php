<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_initialize_payment_for_invoice(): void
    {
        $business = Business::create(['name' => 'Store C', 'slug' => 'store-c']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner C',
            'email' => 'owner@storec.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-1234',
            'customer_name' => 'Alice',
            'amount' => 150000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/payments', [
                'invoice_id' => $invoice->id,
                'payment_method' => 'QRIS',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'payment' => [
                    'id',
                    'payment_number',
                    'expected_amount',
                    'status',
                    'payment_method',
                ],
            ])
            ->assertJsonPath('payment.expected_amount', '150000.00')
            ->assertJsonPath('payment.status', Payment::STATUS_WAITING_PAYMENT);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'expected_amount' => 150000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.created',
        ]);
    }
}
