<?php

namespace App\Domain\Reconciliation\Services;

use App\Models\Payment;
use App\Models\WebhookEvent;

class ReconciliationService
{
    /**
     * Perform 3-way reconciliation across Expected Payment, AI Proof Extraction, and Provider Webhook data.
     */
    public function reconcile(Payment $payment): array
    {
        $payment->load(['invoice', 'proof.extraction', 'validationResult']);

        $expectedAmount = (float) $payment->expected_amount;
        $expectedCurrency = $payment->currency;

        // Source 1: Expected Data
        $expectedSource = [
            'amount' => $expectedAmount,
            'currency' => $expectedCurrency,
            'status' => $payment->status,
            'invoice_number' => $payment->invoice?->invoice_number,
        ];

        // Source 2: AI Proof Data
        $extraction = $payment->proof?->extraction;
        $proofSource = null;
        if ($extraction && $extraction->status === 'COMPLETED') {
            $proofSource = [
                'amount' => $extraction->extracted_amount !== null ? (float) $extraction->extracted_amount : null,
                'currency' => $extraction->extracted_currency,
                'date' => $extraction->extracted_date?->toDateString(),
                'provider' => $extraction->extracted_provider,
                'ref_number' => $extraction->extracted_ref_number,
                'confidence_score' => $extraction->confidence_score,
            ];
        }

        // Source 3: Provider Webhook Data
        $webhookEvent = WebhookEvent::where('business_id', $payment->business_id)
            ->where('status', 'PROCESSED')
            ->where(function ($query) use ($payment) {
                $query->where('payload->payment_number', $payment->payment_number)
                    ->orWhere('payload->reference_number', $payment->payment_number);
            })
            ->latest()
            ->first();

        $providerSource = null;
        if ($webhookEvent) {
            $payload = $webhookEvent->payload;
            $providerSource = [
                'provider' => $webhookEvent->provider,
                'event_type' => $webhookEvent->event_type,
                'amount' => isset($payload['amount']) ? (float) $payload['amount'] : null,
                'status' => $payload['status'] ?? null,
                'transaction_ref' => $payload['transaction_ref'] ?? $payload['reference_number'] ?? null,
                'received_at' => $webhookEvent->created_at->toIso8601String(),
            ];
        }

        // Evaluate 3-Way Reconciliation Status
        $status = $this->determineStatus($expectedSource, $proofSource, $providerSource);

        return [
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'reconciliation_status' => $status,
            'summary' => $this->generateSummary($status, $expectedSource, $proofSource, $providerSource),
            'sources' => [
                'expected' => $expectedSource,
                'proof' => $proofSource,
                'provider' => $providerSource,
            ],
            'reconciled_at' => now()->toIso8601String(),
        ];
    }

    private function determineStatus(array $expected, ?array $proof, ?array $provider): string
    {
        if ($proof === null && $provider === null) {
            return 'NO_DATA';
        }

        if ($proof === null) {
            return 'PROOF_DATA_NOT_FOUND';
        }

        if ($provider === null) {
            return 'PROVIDER_DATA_NOT_FOUND';
        }

        $expectedAmount = $expected['amount'];
        $proofAmount = $proof['amount'];
        $providerAmount = $provider['amount'];

        $proofMatches = $proofAmount !== null && abs($expectedAmount - $proofAmount) < 0.01;
        $providerMatches = $providerAmount !== null && abs($expectedAmount - $providerAmount) < 0.01;

        if ($proofMatches && $providerMatches) {
            return 'ALL_MATCH';
        }

        if (! $proofMatches || ! $providerMatches) {
            return 'AMOUNT_MISMATCH';
        }

        return 'DISCREPANCY_DETECTED';
    }

    private function generateSummary(string $status, array $expected, ?array $proof, ?array $provider): string
    {
        return match ($status) {
            'ALL_MATCH' => 'All 3 sources (Expected Invoice, AI Proof Extraction, and Provider Webhook) match perfectly.',
            'AMOUNT_MISMATCH' => 'Payment amount discrepancy detected between expected amount (Rp'.number_format($expected['amount'], 0, ',', '.').'), AI proof, or provider transaction.',
            'PROVIDER_DATA_NOT_FOUND' => 'Payment proof is present, but no matching transaction data was received from the payment gateway.',
            'PROOF_DATA_NOT_FOUND' => 'Payment proof screenshot has not been uploaded yet.',
            'NO_DATA' => 'No payment proof or gateway transaction data found for this payment.',
            default => 'Discrepancy detected during 3-way reconciliation.',
        };
    }
}
