<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\AIExtractionProviderInterface;
use App\Domain\AI\DTOs\ExtractionResultDTO;
use App\Domain\AI\Providers\GeminiVisionProvider;
use App\Domain\AI\Providers\MockAiVisionProvider;

class AIExtractionService
{
    private AIExtractionProviderInterface $provider;

    public function __construct(?AIExtractionProviderInterface $provider = null)
    {
        $driver = config('services.ai.driver', env('AI_DRIVER', 'gemini'));

        $this->provider = $provider ?? match ($driver) {
            'mock' => new MockAiVisionProvider,
            default => new GeminiVisionProvider,
        };
    }

    public function extractFromProof(string $absoluteImagePath, ?string $originalFilename = null): ExtractionResultDTO
    {
        return $this->provider->extractFromImage($absoluteImagePath, $originalFilename);
    }
}
