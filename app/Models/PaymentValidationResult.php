<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentValidationResult extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'payment_id',
        'is_amount_matched',
        'is_currency_matched',
        'is_date_valid',
        'is_reference_found',
        'discrepancy_details',
    ];

    protected function casts(): array
    {
        return [
            'is_amount_matched' => 'boolean',
            'is_currency_matched' => 'boolean',
            'is_date_valid' => 'boolean',
            'is_reference_found' => 'boolean',
            'discrepancy_details' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
