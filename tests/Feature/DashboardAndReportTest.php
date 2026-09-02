<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_returns_correct_aggregated_metrics(): void
    {
        $business = Business::create(['name' => 'Dash Biz', 'slug' => 'dash-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Dash Owner',
            'email' => 'owner@dash.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-DASH-001',
            'customer_name' => 'Customer 1',
            'amount' => 100000,
            'status' => 'PAID',
        ]);

        // 1 Verified payment
        Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-DASH-001',
            'expected_amount' => 100000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        // 1 Rejected payment
        Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-DASH-002',
            'expected_amount' => 50000,
            'status' => Payment::STATUS_REJECTED,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('summary.total_transactions', 2)
            ->assertJsonPath('summary.total_revenue', 100000)
            ->assertJsonPath('summary.verified_payments', 1)
            ->assertJsonPath('summary.rejected_payments', 1)
            ->assertJsonPath('summary.verification_rate_percentage', 50);
    }

    public function test_reports_transactions_filters_by_date_status_and_amount(): void
    {
        $business = Business::create(['name' => 'Report Biz', 'slug' => 'rep-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Report Owner',
            'email' => 'owner@report.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-REP-001',
            'customer_name' => 'Customer 2',
            'amount' => 150000,
            'status' => 'WAITING_PAYMENT',
        ]);

        Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-REP-001',
            'expected_amount' => 150000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-REP-002',
            'expected_amount' => 300000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Filter by VERIFIED status
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/reports/transactions?status=VERIFIED');

        $response->assertStatus(200)
            ->assertJsonPath('report_summary.total_records', 1)
            ->assertJsonPath('report_summary.total_amount', 150000);
    }
}
