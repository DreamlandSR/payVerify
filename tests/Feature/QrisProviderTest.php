<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrisProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_creation_generates_qris_data(): void
    {
        $business = Business::create(['name' => 'QRIS Biz', 'slug' => 'qris-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner Q',
            'email' => 'owner@qris.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-7788',
            'customer_name' => 'Karen',
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
                'qris' => ['payment_number', 'qris_string', 'qr_code_url', 'amount', 'currency'],
                'payment',
            ])
            ->assertJsonPath('qris.amount', 150000);
    }

    public function test_valid_webhook_signature_processes_event_and_records_audit_log(): void
    {
        $secret = 'mock_qris_secret_123';
        $payloadData = [
            'payment_number' => 'PAY-20260902-123456',
            'status' => 'PAID',
            'amount' => 150000,
            'transaction_ref' => 'REF998877',
            'event_type' => 'payment.succeeded',
        ];

        $jsonPayload = json_encode($payloadData);
        $signature = hash_hmac('sha256', $jsonPayload, $secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/mock_qris',
            [],
            [],
            [],
            [
                'HTTP_X-Webhook-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Webhook payload processed successfully.');

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'mock_qris',
            'event_type' => 'payment.succeeded',
            'status' => 'PROCESSED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'webhook.received',
        ]);
    }

    public function test_invalid_webhook_signature_is_rejected_with_401(): void
    {
        $payloadData = ['payment_number' => 'PAY-000', 'status' => 'PAID'];
        $jsonPayload = json_encode($payloadData);

        $response = $this->call(
            'POST',
            '/api/webhooks/mock_qris',
            [],
            [],
            [],
            [
                'HTTP_X-Webhook-Signature' => 'invalid_signature_123',
                'CONTENT_TYPE' => 'application/json',
            ],
            $jsonPayload
        );

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid webhook signature.');

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'mock_qris',
            'status' => 'FAILED',
        ]);
    }
}
