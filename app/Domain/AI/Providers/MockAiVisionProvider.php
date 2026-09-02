<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIExtractionProviderInterface;
use App\Domain\AI\DTOs\ExtractionResultDTO;

class MockAiVisionProvider implements AIExtractionProviderInterface
{
    public function __construct(
        private readonly ?float $mockAmount = null,
        private readonly float $confidenceScore = 0.96,
        private readonly bool $shouldFail = false
    ) {}

    public function extractFromImage(string $absoluteImagePath): ExtractionResultDTO
    {
        if ($this->shouldFail) {
            return new ExtractionResultDTO(
                success: false,
                confidenceScore: 0.0,
                errorMessage: 'AI image processing failed: Image is unreadable or corrupted.'
            );
        }

        return new ExtractionResultDTO(
            success: true,
            amount: $this->mockAmount ?? 125000.00,
            currency: 'IDR',
            date: date('Y-m-d'),
            time: '14:32:00',
            provider: 'BCA',
            referenceNumber: 'TRX'.rand(1000000, 9999999),
            merchantName: 'Mock Store Merchant',
            confidenceScore: $this->confidenceScore,
            rawOcrText: 'TRANSFER BERHASIL BCA Rp 125.000 REFF TRX'.rand(1000000, 9999999)
        );
    }
}
