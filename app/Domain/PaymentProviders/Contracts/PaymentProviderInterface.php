<?php

namespace App\Domain\PaymentProviders\Contracts;

use App\Domain\PaymentProviders\DTOs\QrisPaymentDataDTO;
use App\Domain\PaymentProviders\DTOs\WebhookResultDTO;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    /**
     * Create/generate QRIS payment details.
     */
    public function createQrisPayment(Payment $payment): QrisPaymentDataDTO;

    /**
     * Check payment status from payment provider.
     */
    public function checkPaymentStatus(Payment $payment): array;

    /**
     * Verify incoming webhook request signature.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Process incoming webhook request payload.
     */
    public function processWebhook(Request $request): WebhookResultDTO;
}
