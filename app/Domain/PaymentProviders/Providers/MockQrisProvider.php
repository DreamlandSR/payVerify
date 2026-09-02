<?php

namespace App\Domain\PaymentProviders\Providers;

use App\Domain\PaymentProviders\Contracts\PaymentProviderInterface;
use App\Domain\PaymentProviders\DTOs\QrisPaymentDataDTO;
use App\Domain\PaymentProviders\DTOs\WebhookResultDTO;
use App\Models\Payment;
use Illuminate\Http\Request;

class MockQrisProvider implements PaymentProviderInterface
{
    private string $webhookSecret;

    public function __construct(?string $webhookSecret = null)
    {
        $this->webhookSecret = $webhookSecret ?? config('services.payment_provider.secret', 'mock_qris_secret_123');
    }

    public function createQrisPayment(Payment $payment): QrisPaymentDataDTO
    {
        $qrisString = '00020101021226680016ID.CO.QRIS.WWW0118936000000000000000520458125303360540'.(int) $payment->expected_amount.'5802ID5913MOCK MERCHANT6007JAKARTA6304A1B2';
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($qrisString);

        return new QrisPaymentDataDTO(
            paymentNumber: $payment->payment_number,
            qrisString: $qrisString,
            qrCodeUrl: $qrCodeUrl,
            amount: (float) $payment->expected_amount,
            currency: $payment->currency,
            expiresAt: $payment->expires_at?->toIso8601String() ?? now()->addHours(24)->toIso8601String()
        );
    }

    public function checkPaymentStatus(Payment $payment): array
    {
        return [
            'payment_number' => $payment->payment_number,
            'status' => $payment->status,
            'amount' => (float) $payment->expected_amount,
            'provider' => 'MOCK_QRIS',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    public function processWebhook(Request $request): WebhookResultDTO
    {
        if (! $this->verifyWebhookSignature($request)) {
            return new WebhookResultDTO(
                validSignature: false,
                errorMessage: 'Invalid webhook HMAC signature.'
            );
        }

        $data = $request->json()->all();

        return new WebhookResultDTO(
            validSignature: true,
            paymentNumber: $data['payment_number'] ?? null,
            status: $data['status'] ?? 'PAID',
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            transactionRef: $data['transaction_ref'] ?? null,
            eventType: $data['event_type'] ?? 'payment.succeeded'
        );
    }
}
