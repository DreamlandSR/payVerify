<?php

namespace App\Domain\AI\DTOs;

class ExtractionResultDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly ?float $amount = null,
        public readonly string $currency = 'IDR',
        public readonly ?string $date = null,
        public readonly ?string $time = null,
        public readonly ?string $provider = null,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $merchantName = null,
        public readonly float $confidenceScore = 0.0,
        public readonly ?string $rawOcrText = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'extracted_amount' => $this->amount,
            'extracted_currency' => $this->currency,
            'extracted_date' => $this->date,
            'extracted_time' => $this->time,
            'extracted_provider' => $this->provider,
            'extracted_ref_number' => $this->referenceNumber,
            'extracted_merchant_name' => $this->merchantName,
            'confidence_score' => $this->confidenceScore,
            'raw_ocr_text' => $this->rawOcrText,
            'error_message' => $this->errorMessage,
        ];
    }
}
