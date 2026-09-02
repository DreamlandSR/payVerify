<?php

namespace App\Domain\Validation\Services;

use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentProof;
use App\Models\PaymentRiskAssessment;

class RiskAnalysisService
{
    /**
     * Evaluate risk indicators for a payment and store risk assessment.
     */
    public function assess(Payment $payment, PaymentExtraction $extraction): PaymentRiskAssessment
    {
        $riskFactors = [];

        // 1. Amount mismatch
        if ($extraction->extracted_amount !== null) {
            $diff = abs((float) $payment->expected_amount - (float) $extraction->extracted_amount);
            if ($diff > 0.01) {
                $riskFactors[] = [
                    'indicator' => 'amount_mismatch',
                    'severity' => 'HIGH',
                    'message' => 'Detected amount does not match expected amount. Difference: Rp'.number_format($diff, 0, ',', '.'),
                ];
            }
        } else {
            $riskFactors[] = [
                'indicator' => 'unreadable_amount',
                'severity' => 'MEDIUM',
                'message' => 'AI could not extract the payment amount from the proof image.',
            ];
        }

        // 2. Low confidence score
        if ($extraction->confidence_score < 0.7) {
            $riskFactors[] = [
                'indicator' => 'low_confidence',
                'severity' => 'MEDIUM',
                'message' => 'AI confidence is low ('.round($extraction->confidence_score * 100).'%). Manual verification strongly recommended.',
            ];
        }

        // 3. Old transaction date
        if ($extraction->extracted_date) {
            $daysDiff = (int) abs($payment->created_at->startOfDay()->diffInDays($extraction->extracted_date->startOfDay()));
            if ($daysDiff > 7) {
                $riskFactors[] = [
                    'indicator' => 'old_transaction_date',
                    'severity' => 'MEDIUM',
                    'message' => 'Transaction date is '.$daysDiff.' day(s) old.',
                ];
            }
        }

        // 4. Duplicate proof detection (by file hash)
        $isDuplicateProof = false;
        $duplicateProofId = null;
        $proof = $payment->proof;

        if ($proof) {
            $duplicate = PaymentProof::where('file_hash', $proof->file_hash)
                ->where('id', '!=', $proof->id)
                ->first();

            if ($duplicate) {
                $isDuplicateProof = true;
                $duplicateProofId = $duplicate->id;
                $riskFactors[] = [
                    'indicator' => 'duplicate_proof',
                    'severity' => 'HIGH',
                    'message' => 'This payment proof appears identical to proof #'.$duplicate->id.' (Payment #'.$duplicate->payment_id.').',
                ];
            }
        }

        // 5. Duplicate reference number
        if ($extraction->extracted_ref_number) {
            $duplicateRef = PaymentExtraction::where('extracted_ref_number', $extraction->extracted_ref_number)
                ->where('id', '!=', $extraction->id)
                ->first();

            if ($duplicateRef) {
                $riskFactors[] = [
                    'indicator' => 'duplicate_reference',
                    'severity' => 'HIGH',
                    'message' => 'Reference number "'.$extraction->extracted_ref_number.'" was previously used in another payment.',
                ];
            }
        }

        // 6. Missing transaction information
        $missingFields = [];
        if (! $extraction->extracted_date) {
            $missingFields[] = 'date';
        }
        if (! $extraction->extracted_provider) {
            $missingFields[] = 'provider';
        }
        if (! $extraction->extracted_ref_number) {
            $missingFields[] = 'reference_number';
        }
        if (count($missingFields) > 0) {
            $riskFactors[] = [
                'indicator' => 'missing_information',
                'severity' => 'LOW',
                'message' => 'Missing transaction fields: '.implode(', ', $missingFields),
            ];
        }

        // Calculate overall risk level
        $riskLevel = $this->calculateRiskLevel($riskFactors);

        // Delete any existing risk assessment for this payment
        PaymentRiskAssessment::where('payment_id', $payment->id)->delete();

        return PaymentRiskAssessment::create([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors ?: null,
            'is_duplicate_proof' => $isDuplicateProof,
            'duplicate_proof_id' => $duplicateProofId,
        ]);
    }

    /**
     * Determine risk level from the highest severity among all risk factors.
     */
    private function calculateRiskLevel(array $riskFactors): string
    {
        $hasHigh = false;
        $hasMedium = false;

        foreach ($riskFactors as $factor) {
            if ($factor['severity'] === 'HIGH') {
                $hasHigh = true;
            }
            if ($factor['severity'] === 'MEDIUM') {
                $hasMedium = true;
            }
        }

        if ($hasHigh) {
            return 'HIGH';
        }
        if ($hasMedium) {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}
