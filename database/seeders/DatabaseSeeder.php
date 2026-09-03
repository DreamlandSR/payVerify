<?php

namespace Database\Seeders;

use App\Domain\Validation\Services\PaymentValidationService;
use App\Domain\Validation\Services\RiskAnalysisService;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentProof;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Business (Donasi Peduli Foundation)
        $business = Business::create([
            'name' => 'Yayasan Donasi Peduli',
            'slug' => 'donasi-peduli',
        ]);

        // 2. Create Owner Account (Admin Foundation)
        $owner = User::create([
            'business_id' => $business->id,
            'name' => 'Admin Donasi',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        // 3. Create Staff Account (Staff Verifier)
        User::create([
            'business_id' => $business->id,
            'name' => 'Staff Verifikator',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        // 4. Create Donor/User Account
        User::create([
            'business_id' => $business->id,
            'name' => 'Donatur Dermawan',
            'email' => 'donor@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // 5. Create Subscription
        Subscription::create([
            'business_id' => $business->id,
            'plan_name' => 'FREE',
            'max_verifications_per_month' => 50,
            'current_month_usage' => 1,
        ]);

        // 6. Sample Pending Donation Payment (Ready for Proof Upload test)
        $invPending = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-DONASI-0003',
            'customer_name' => 'Donatur Dermawan',
            'amount' => 150000,
            'status' => 'WAITING_PAYMENT',
        ]);

        Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $invPending->id,
            'payment_number' => 'PAY-DONASI-000003',
            'expected_amount' => 150000,
            'status' => Payment::STATUS_WAITING_PAYMENT,
            'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=DONASI150000',
        ]);

        // 7. Sample Payment Awaiting Verification
        $inv1 = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-DONASI-0001',
            'customer_name' => 'Budi Santoso',
            'amount' => 125000,
            'status' => 'WAITING_PAYMENT',
        ]);

        $payment1 = Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $inv1->id,
            'payment_number' => 'PAY-DONASI-000001',
            'expected_amount' => 125000,
            'status' => Payment::STATUS_WAITING_VERIFICATION,
        ]);

        $proof1 = PaymentProof::create([
            'business_id' => $business->id,
            'payment_id' => $payment1->id,
            'file_path' => 'private/payment_proofs/sample1.jpg',
            'file_name' => 'sample1.jpg',
            'file_hash' => 'hash_sample_111',
            'file_size' => 2048,
            'mime_type' => 'image/jpeg',
        ]);

        $extraction1 = PaymentExtraction::create([
            'business_id' => $business->id,
            'payment_proof_id' => $proof1->id,
            'raw_ocr_text' => 'BCA TRANSFER Rp 125.000 SUCCESS REF: ABC123456',
            'extracted_amount' => 125000,
            'extracted_currency' => 'IDR',
            'extracted_date' => now()->toDateString(),
            'extracted_provider' => 'BCA',
            'extracted_ref_number' => 'ABC123456',
            'extracted_merchant_name' => 'Yayasan Donasi Peduli',
            'confidence_score' => 0.96,
            'status' => 'COMPLETED',
        ]);

        (new PaymentValidationService)->validate($payment1, $extraction1);
        (new RiskAnalysisService)->assess($payment1, $extraction1);

        // 8. Sample Verified Donation Payment
        $inv2 = Invoice::create([
            'business_id' => $business->id,
            'invoice_number' => 'INV-DONASI-0002',
            'customer_name' => 'Siti Rahma',
            'amount' => 250000,
            'status' => 'PAID',
        ]);

        Payment::create([
            'business_id' => $business->id,
            'invoice_id' => $inv2->id,
            'payment_number' => 'PAY-DONASI-000002',
            'expected_amount' => 250000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by_user_id' => $owner->id,
        ]);
    }
}
