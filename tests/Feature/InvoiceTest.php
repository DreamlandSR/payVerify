<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_invoice_for_their_business(): void
    {
        $business = Business::create(['name' => 'Store A', 'slug' => 'store-a']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner A',
            'email' => 'owner@storea.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/invoices', [
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
                'amount' => 125000,
                'description' => 'Pembelian Kopi & Snack',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'invoice' => [
                    'id',
                    'invoice_number',
                    'customer_name',
                    'amount',
                    'status',
                ],
            ])
            ->assertJsonPath('invoice.amount', '125000.00')
            ->assertJsonPath('invoice.status', 'WAITING_PAYMENT');

        $this->assertDatabaseHas('invoices', [
            'business_id' => $business->id,
            'customer_name' => 'John Doe',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invoice.created',
        ]);
    }

    public function test_user_can_list_invoices(): void
    {
        $business = Business::create(['name' => 'Store B', 'slug' => 'store-b']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Owner B',
            'email' => 'owner@storeb.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-20260902-0001',
            'customer_name' => 'Jane Smith',
            'amount' => 50000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/invoices');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'Jane Smith');
    }

    public function test_user_cannot_access_invoices_from_another_business(): void
    {
        $bizA = Business::create(['name' => 'Business A', 'slug' => 'biz-a']);
        $userA = User::create([
            'business_id' => $bizA->id,
            'name' => 'User A',
            'email' => 'user@a.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $bizB = Business::create(['name' => 'Business B', 'slug' => 'biz-b']);
        $invoiceB = Invoice::create([
            'business_id' => $bizB->id,
            'invoice_number' => 'INV-20260902-9999',
            'customer_name' => 'Secret Customer',
            'amount' => 999000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $tokenA = $userA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/invoices/'.$invoiceB->id);

        $response->assertStatus(404);
    }
}
