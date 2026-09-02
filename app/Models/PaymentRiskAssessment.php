<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRiskAssessment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'payment_id',
        'risk_level',
        'risk_factors',
        'is_duplicate_proof',
        'duplicate_proof_id',
    ];

    protected function casts(): array
    {
        return [
            'risk_factors' => 'array',
            'is_duplicate_proof' => 'boolean',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function duplicateProof(): BelongsTo
    {
        return $this->belongsTo(PaymentProof::class, 'duplicate_proof_id');
    }
}
