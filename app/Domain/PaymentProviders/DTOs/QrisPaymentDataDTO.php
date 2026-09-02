<?php

namespace App\Domain\PaymentProviders\DTOs;

class QrisPaymentDataDTO
{
    public function __construct(
        public readonly string $paymentNumber,
        public readonly string $qrisString,
        public readonly string $qrCodeUrl,
        public readonly float $amount,
        public readonly string $currency = 'IDR',
        public readonly ?string $expiresAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'payment_number' => $this->paymentNumber,
            'qris_string' => $this->qrisString,
            'qr_code_url' => $this->qrCodeUrl,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'expires_at' => $this->expiresAt,
        ];
    }
}
