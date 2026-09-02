<?php

namespace App\Domain\Validation\Services;

use App\Models\Payment;
use App\Models\PaymentExtraction;
use App\Models\PaymentValidationResult;

class PaymentValidationService
{
    /**
     * Compare expected payment data against AI-extracted data and store validation results.
     */
    public function validate(Payment $payment, PaymentExtraction $extraction): PaymentValidationResult
    {
        $discrepancies = [];

        // Amount comparison
        $isAmountMatched = false;
        if ($extraction->extracted_amount !== null) {
            $isAmountMatched = abs((float) $payment->expected_amount - (float) $extraction->extracted_amount) < 0.01;
            if (! $isAmountMatched) {
                $discrepancies[] = [
                    'field' => 'amount',
                    'expected' => (float) $payment->expected_amount,
                    'detected' => (float) $extraction->extracted_amount,
                    'message' => 'Amount does not match. Expected: Rp'.number_format((float) $payment->expected_amount, 0, ',', '.').', Detected: Rp'.number_format((float) $extraction->extracted_amount, 0, ',', '.'),
                ];
            }
        } else {
            $discrepancies[] = [
                'field' => 'amount',
                'expected' => (float) $payment->expected_amount,
                'detected' => null,
                'message' => 'Amount could not be extracted from payment proof.',
            ];
        }

        // Currency comparison
        $isCurrencyMatched = strtoupper($payment->currency) === strtoupper($extraction->extracted_currency ?? '');
        if (! $isCurrencyMatched) {
            $discrepancies[] = [
                'field' => 'currency',
                'expected' => $payment->currency,
                'detected' => $extraction->extracted_currency,
                'message' => 'Currency mismatch.',
            ];
        }

        // Date validation — extracted date should be within 3 days of payment creation if present
        $isDateValid = true;
        if ($extraction->extracted_date) {
            $daysDiff = (int) abs($payment->created_at->startOfDay()->diffInDays($extraction->extracted_date->startOfDay()));
            $isDateValid = $daysDiff <= 3;
            if (! $isDateValid) {
                $discrepancies[] = [
                    'field' => 'date',
                    'expected' => $payment->created_at->toDateString(),
                    'detected' => $extraction->extracted_date->toDateString(),
                    'message' => 'Transaction date is '.$daysDiff.' day(s) from payment creation date.',
                ];
            }
        }

        // Reference number presence
        $isReferenceFound = ! empty($extraction->extracted_ref_number);

        // Delete any existing validation result for this payment
        PaymentValidationResult::where('payment_id', $payment->id)->delete();

        return PaymentValidationResult::create([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'is_amount_matched' => $isAmountMatched,
            'is_currency_matched' => $isCurrencyMatched,
            'is_date_valid' => $isDateValid,
            'is_reference_found' => $isReferenceFound,
            'discrepancy_details' => $discrepancies ?: null,
        ]);
    }
}
