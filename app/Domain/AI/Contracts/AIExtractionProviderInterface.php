<?php

namespace App\Domain\AI\Contracts;

use App\Domain\AI\DTOs\ExtractionResultDTO;

interface AIExtractionProviderInterface
{
    /**
     * Extract structured payment details from payment proof image.
     */
    public function extractFromImage(string $absoluteImagePath): ExtractionResultDTO;
}
