<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIExtractionProviderInterface;
use App\Domain\AI\DTOs\ExtractionResultDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiVisionProvider implements AIExtractionProviderInterface
{
    public function __construct(
        private readonly ?string $apiKey = null
    ) {}

    public function extractFromImage(string $absoluteImagePath): ExtractionResultDTO
    {
        $key = $this->apiKey ?? config('services.gemini.key', env('GEMINI_API_KEY'));

        if (! $key) {
            // Fallback to Mock provider if no API key is set
            return (new MockAiVisionProvider)->extractFromImage($absoluteImagePath);
        }

        if (! file_exists($absoluteImagePath)) {
            return new ExtractionResultDTO(
                success: false,
                confidenceScore: 0.0,
                errorMessage: 'Payment proof image file not found.'
            );
        }

        try {
            $mimeType = mime_content_type($absoluteImagePath) ?: 'image/jpeg';
            $base64Image = base64_encode(file_get_contents($absoluteImagePath));

            $prompt = <<<'PROMPT'
You are an expert OCR payment verification system. Analyze this payment proof/receipt image and extract payment information into strict JSON format.
Respond ONLY with a valid JSON object. Do not add markdown codeblocks, commentary or formatting outside JSON.

Required JSON Schema:
{
  "amount": 125000,
  "currency": "IDR",
  "date": "YYYY-MM-DD",
  "time": "HH:MM",
  "provider": "BCA",
  "reference_number": "REF123456",
  "merchant_name": "Merchant Name",
  "confidence_score": 0.95,
  "raw_ocr_text": "text extracted"
}
PROMPT;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$key}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Image,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('Gemini API call failed: '.$response->body());

                return new ExtractionResultDTO(
                    success: false,
                    errorMessage: 'Gemini AI service API error: '.$response->status()
                );
            }

            $jsonResponse = $response->json();
            $text = $jsonResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));

            $data = json_decode($text, true);
            if (! is_array($data)) {
                return new ExtractionResultDTO(
                    success: false,
                    rawOcrText: $text,
                    errorMessage: 'Failed to parse JSON response from Gemini AI.'
                );
            }

            return new ExtractionResultDTO(
                success: true,
                amount: isset($data['amount']) ? (float) $data['amount'] : null,
                currency: $data['currency'] ?? 'IDR',
                date: $data['date'] ?? null,
                time: $data['time'] ?? null,
                provider: $data['provider'] ?? null,
                referenceNumber: $data['reference_number'] ?? null,
                merchantName: $data['merchant_name'] ?? null,
                confidenceScore: isset($data['confidence_score']) ? (float) $data['confidence_score'] : 0.85,
                rawOcrText: $data['raw_ocr_text'] ?? $text
            );
        } catch (\Throwable $e) {
            Log::error('Gemini Vision exception: '.$e->getMessage());

            return new ExtractionResultDTO(
                success: false,
                errorMessage: $e->getMessage()
            );
        }
    }
}
