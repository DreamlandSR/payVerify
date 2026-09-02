<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_view_current_subscription_and_usage(): void
    {
        $business = Business::create(['name' => 'Sub Biz', 'slug' => 'sub-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Sub Owner',
            'email' => 'owner@sub.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 10,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('subscription.plan_name', 'FREE')
            ->assertJsonPath('usage.current', 10)
            ->assertJsonPath('usage.limit', 50)
            ->assertJsonPath('usage.percentage', 20);
    }

    public function test_owner_can_upgrade_subscription_plan(): void
    {
        $business = Business::create(['name' => 'Upgrade Biz', 'slug' => 'upgrade-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Upgrade Owner',
            'email' => 'owner@upgrade.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 50,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/subscription/upgrade', [
                'plan_name' => 'STARTER',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('subscription.plan_name', 'STARTER')
            ->assertJsonPath('subscription.max_verifications_per_month', 500);

        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $business->id,
            'plan_name' => 'STARTER',
            'max_verifications_per_month' => 500,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'subscription.upgraded',
        ]);
    }

    public function test_verification_is_blocked_when_monthly_quota_is_exceeded(): void
    {
        $business = Business::create(['name' => 'Quota Exceeded Biz', 'slug' => 'quota-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Quota Owner',
            'email' => 'owner@quota.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 50, // Limit reached!
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-QUOTA-001',
            'customer_name' => 'Customer Q',
            'amount' => 100000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-QUOTA-001',
            'expected_amount' => 100000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/verify", [
                'decision' => 'VALID',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_LIMIT_EXCEEDED');
    }

    public function test_verification_succeeds_after_upgrading_plan(): void
    {
        $business = Business::create(['name' => 'Quota Recovery Biz', 'slug' => 'recovery-biz']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Recovery Owner',
            'email' => 'owner@recovery.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
        $sub = Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 50, // Limit reached
        ]);

        $invoice = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-REC-999',
            'customer_name' => 'Customer R',
            'amount' => 200000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-REC-999',
            'expected_amount' => 200000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        // Upgrade plan to STARTER
        $sub->update([
            'plan_name' => 'STARTER',
            'max_verifications_per_month' => 500,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/payments/{$payment->id}/verify", [
                'decision' => 'VALID',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('decision', 'VALID');
    }
}
