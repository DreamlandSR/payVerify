<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIExtractionProviderInterface;
use App\Domain\AI\DTOs\ExtractionResultDTO;
use Illuminate\Support\Str;

class MockAiVisionProvider implements AIExtractionProviderInterface
{
    public function __construct(
        private readonly ?float $mockAmount = null,
        private readonly float $confidenceScore = 0.96,
        private readonly bool $shouldFail = false
    ) {}

    public function extractFromImage(string $absoluteImagePath, ?string $originalFilename = null): ExtractionResultDTO
    {
        if ($this->shouldFail) {
            return new ExtractionResultDTO(
                success: false,
                confidenceScore: 0.0,
                errorMessage: 'AI image processing failed: Image is unreadable or corrupted.'
            );
        }

        $filename = Str::lower($originalFilename ?? basename($absoluteImagePath));

        // 1. Check if filename contains explicit non-receipt keywords (e.g. logo, spongebob, avatar, etc.)
        $invalidKeywords = ['spongebob', 'logo', 'avatar', 'banner', 'illustration', 'vector', 'drawing', 'icon', 'meme'];
        foreach ($invalidKeywords as $kw) {
            if (Str::contains($filename, $kw)) {
                return new ExtractionResultDTO(
                    success: false,
                    confidenceScore: 0.1,
                    errorMessage: '❌ FOTO STRUK TIDAK VALID: AI tidak dapat menemukan data nominal transfer pada gambar yang diunggah (Terdeteksi sebagai gambar non-struk).'
                );
            }
        }

        // 2. Parse mock amount if passed explicitly in test/code
        if ($this->mockAmount !== null) {
            return new ExtractionResultDTO(
                success: true,
                amount: $this->mockAmount,
                currency: 'IDR',
                date: date('Y-m-d'),
                time: date('H:i:s'),
                provider: 'BCA',
                referenceNumber: 'TRX'.rand(1000000, 9999999),
                merchantName: 'Yayasan Donasi Peduli',
                confidenceScore: $this->confidenceScore,
                rawOcrText: 'TRANSFER BERHASIL Rp '.number_format($this->mockAmount, 0, ',', '.')
            );
        }

        // 3. Must contain receipt / financial indicator keywords
        $receiptKeywords = ['transfer', 'bukti', 'struk', 'bayar', 'donasi', 'receipt', 'sample', 'proof', 'test', 'bca', 'mandiri', 'bri', 'bni', 'gopay', 'ovo', 'dana', 'shopeepay'];
        $hasReceiptKeyword = false;
        foreach ($receiptKeywords as $kw) {
            if (Str::contains($filename, $kw)) {
                $hasReceiptKeyword = true;
                break;
            }
        }

        if (! $hasReceiptKeyword) {
            return new ExtractionResultDTO(
                success: false,
                confidenceScore: 0.15,
                errorMessage: '❌ FOTO STRUK TIDAK VALID / TIDAK TERBACA: AI tidak dapat menemukan angka nominal transfer pada gambar ini. Silakan unggah struk transfer yang sah.'
            );
        }

        // 4. Extract amount from filename (e.g., receipt_250000.png, transfer_50k.jpg)
        $detectedAmount = null;
        if (preg_match('/(\d{5,9})/', $filename, $matches)) {
            $detectedAmount = (float) $matches[1];
        } elseif (preg_match('/(\d+)k/i', $filename, $matches)) {
            $detectedAmount = (float) ($matches[1] * 1000);
        } else {
            $detectedAmount = 125000.00;
        }

        // 5. Parse provider from filename
        $providers = ['bca' => 'BCA', 'mandiri' => 'MANDIRI', 'bri' => 'BRI', 'bni' => 'BNI', 'gopay' => 'GOPAY', 'ovo' => 'OVO', 'dana' => 'DANA', 'shopeepay' => 'SHOPEEPAY'];
        $detectedProvider = 'QRIS / Transfer Bank';
        foreach ($providers as $key => $name) {
            if (Str::contains($filename, $key)) {
                $detectedProvider = $name;
                break;
            }
        }

        return new ExtractionResultDTO(
            success: true,
            amount: $detectedAmount,
            currency: 'IDR',
            date: date('Y-m-d'),
            time: date('H:i:s'),
            provider: $detectedProvider,
            referenceNumber: 'TRX'.rand(1000000, 9999999),
            merchantName: 'Yayasan Donasi Peduli',
            confidenceScore: $this->confidenceScore,
            rawOcrText: "TRANSFER BERHASIL {$detectedProvider} Rp ".number_format($detectedAmount, 0, ',', '.').' REFF TRX'.rand(1000000, 9999999)
        );
    }
}
