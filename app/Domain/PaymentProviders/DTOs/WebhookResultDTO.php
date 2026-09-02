<?php

namespace App\Domain\PaymentProviders\DTOs;

class WebhookResultDTO
{
    public function __construct(
        public readonly bool $validSignature,
        public readonly ?string $paymentNumber = null,
        public readonly ?string $status = null,
        public readonly ?float $amount = null,
        public readonly ?string $transactionRef = null,
        public readonly ?string $eventType = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
