<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createDonorWithDonations(): array
    {
        $business = Business::create(['name' => 'Yayasan Test', 'slug' => 'yayasan-test']);

        $donor = User::create([
            'business_id' => $business->id,
            'name' => 'Donatur Test',
            'email' => 'donatur@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-DONOR-001',
            'customer_name' => 'Donatur Test',
            'customer_email' => 'donatur@test.com',
            'amount' => 250000,
            'status' => 'PAID',
        ]);

        $verifiedPayment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-DONOR-001',
            'expected_amount' => 250000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $invoice2 = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-DONOR-002',
            'customer_name' => 'Donatur Test',
            'customer_email' => 'donatur@test.com',
            'amount' => 100000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $pendingPayment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice2->id,
            'payment_number' => 'PAY-DONOR-002',
            'expected_amount' => 100000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
        ]);

        return compact('business', 'donor', 'verifiedPayment', 'pendingPayment');
    }

    public function test_donor_can_view_their_donation_stats(): void
    {
        $data = $this->createDonorWithDonations();

        $response = $this->actingAs($data['donor'])
            ->getJson('/api/donor/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'total_contributed',
                'total_verified_donations',
                'total_pending_donations',
                'total_transactions',
            ]);
    }

    public function test_donor_can_list_their_donations(): void
    {
        $data = $this->createDonorWithDonations();

        $response = $this->actingAs($data['donor'])
            ->getJson('/api/donor/donations');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'payment_number',
                        'expected_amount',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_donor_api(): void
    {
        $this->getJson('/api/donor/stats')->assertUnauthorized();
        $this->getJson('/api/donor/donations')->assertUnauthorized();
    }
}
