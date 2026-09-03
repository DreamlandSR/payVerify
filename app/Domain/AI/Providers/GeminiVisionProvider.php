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

    public function extractFromImage(string $absoluteImagePath, ?string $originalFilename = null): ExtractionResultDTO
    {
        $key = $this->apiKey ?? config('services.gemini.key', env('GEMINI_API_KEY'));

        if (! $key) {
            // Fallback to Mock provider if API key is empty
            return (new MockAiVisionProvider)->extractFromImage($absoluteImagePath, $originalFilename);
        }

        if (! file_exists($absoluteImagePath)) {
            return new ExtractionResultDTO(
                success: false,
                confidenceScore: 0.0,
                errorMessage: 'Payment proof image file not found.'
            );
        }

        try {
            $imagePayload = $this->prepareBase64Image($absoluteImagePath);
            $mimeType = $imagePayload['mime_type'];
            $base64Image = $imagePayload['base64'];

            $prompt = <<<'PROMPT'
You are an expert OCR payment verification system. Analyze this image carefully.
CRITICAL INSTRUCTION:
1. Determine if this image is a valid bank transfer receipt, E-Wallet screenshot, or QRIS payment proof.
2. If the image is NOT a valid payment proof/receipt, or if no payment amount/transfer amount is found, set "is_valid_receipt": false, "amount": null, and describe the reason in "error_message".
3. If it IS a valid payment proof, extract the exact transferred amount as a number (e.g. 50000, 125000, 250000, 1552500), currency, date, provider name (BCA, Mandiri, BRI, BNI, GoPay, OVO, DANA, ShopeePay, etc.), reference number, and merchant name.

Respond ONLY with a valid raw JSON object. Do not wrap in markdown code blocks or add text outside JSON.

JSON Schema:
{
  "is_valid_receipt": true,
  "amount": 125000,
  "currency": "IDR",
  "date": "YYYY-MM-DD",
  "time": "HH:MM",
  "provider": "BCA",
  "reference_number": "REF123456",
  "merchant_name": "Merchant Name",
  "confidence_score": 0.95,
  "error_message": null,
  "raw_ocr_text": "all extracted text from image"
}
PROMPT;

            $models = ['gemini-2.5-flash', 'gemini-1.5-flash-latest', 'gemini-2.0-flash-exp'];
            $response = null;

            foreach ($models as $model) {
                $response = Http::timeout(45)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
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

                if ($response->successful()) {
                    break;
                }
            }

            if (! $response || $response->failed()) {
                Log::error('Gemini API call failed: '.($response ? $response->body() : 'No response'));

                // Fallback to Mock provider on API error
                return (new MockAiVisionProvider)->extractFromImage($absoluteImagePath, $originalFilename);
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

            $isValidReceipt = ($data['is_valid_receipt'] ?? true) && isset($data['amount']) && (float) $data['amount'] > 0;

            return new ExtractionResultDTO(
                success: $isValidReceipt,
                amount: isset($data['amount']) ? (float) $data['amount'] : null,
                currency: $data['currency'] ?? 'IDR',
                date: $data['date'] ?? null,
                time: $data['time'] ?? null,
                provider: $data['provider'] ?? null,
                referenceNumber: $data['reference_number'] ?? null,
                merchantName: $data['merchant_name'] ?? null,
                confidenceScore: isset($data['confidence_score']) ? (float) $data['confidence_score'] : 0.85,
                rawOcrText: $data['raw_ocr_text'] ?? $text,
                errorMessage: $isValidReceipt ? null : ($data['error_message'] ?? '❌ FOTO STRUK TIDAK VALID: Gemini AI Vision tidak menemukan nominal transfer yang sah pada foto ini.')
            );
        } catch (\Throwable $e) {
            Log::error('Gemini Vision exception: '.$e->getMessage());

            return new ExtractionResultDTO(
                success: false,
                errorMessage: $e->getMessage()
            );
        }
    }

    private function prepareBase64Image(string $absoluteImagePath): array
    {
        $mimeType = mime_content_type($absoluteImagePath) ?: 'image/jpeg';

        if (function_exists('imagecreatefromstring') && filesize($absoluteImagePath) > 300 * 1024) {
            try {
                $imageData = file_get_contents($absoluteImagePath);
                $src = @imagecreatefromstring($imageData);
                if ($src !== false) {
                    $width = imagesx($src);
                    $height = imagesy($src);
                    $maxDim = 1200;

                    if ($width > $maxDim || $height > $maxDim) {
                        $ratio = min($maxDim / $width, $maxDim / $height);
                        $newW = (int) ($width * $ratio);
                        $newH = (int) ($height * $ratio);

                        $dst = imagecreatetruecolor($newW, $newH);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

                        ob_start();
                        imagejpeg($dst, null, 82);
                        $compressedData = ob_get_clean();

                        imagedestroy($src);
                        imagedestroy($dst);

                        if ($compressedData) {
                            return [
                                'mime_type' => 'image/jpeg',
                                'base64' => base64_encode($compressedData),
                            ];
                        }
                    }
                    imagedestroy($src);
                }
            } catch (\Throwable $e) {
                // fallback to raw image
            }
        }

        return [
            'mime_type' => $mimeType,
            'base64' => base64_encode(file_get_contents($absoluteImagePath)),
        ];
    }
}
