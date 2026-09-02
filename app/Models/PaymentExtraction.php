<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentExtraction extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'payment_proof_id',
        'raw_ocr_text',
        'extracted_amount',
        'extracted_currency',
        'extracted_date',
        'extracted_time',
        'extracted_provider',
        'extracted_ref_number',
        'extracted_merchant_name',
        'confidence_score',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'extracted_amount' => 'decimal:2',
            'extracted_date' => 'date',
            'confidence_score' => 'float',
        ];
    }

    public function proof(): BelongsTo
    {
        return $this->belongsTo(PaymentProof::class, 'payment_proof_id');
    }
}
