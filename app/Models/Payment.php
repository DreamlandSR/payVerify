<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_WAITING_PAYMENT = 'WAITING_PAYMENT';

    public const STATUS_PROOF_UPLOADED = 'PROOF_UPLOADED';

    public const STATUS_AI_PROCESSING = 'AI_PROCESSING';

    public const STATUS_WAITING_VERIFICATION = 'WAITING_VERIFICATION';

    public const STATUS_VERIFIED = 'VERIFIED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_AI_PROCESSING_FAILED = 'AI_PROCESSING_FAILED';

    protected $fillable = [
        'business_id',
        'invoice_id',
        'payment_number',
        'expected_amount',
        'currency',
        'status',
        'payment_method',
        'qr_code_url',
        'expires_at',
        'verified_at',
        'verified_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function proof(): HasOne
    {
        return $this->hasOne(PaymentProof::class);
    }

    /**
     * Generate a unique payment reference number.
     */
    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY-'.date('Ymd').'-';
        $random = Str::upper(Str::random(6));

        while (static::where('payment_number', $prefix.$random)->exists()) {
            $random = Str::upper(Str::random(6));
        }

        return $prefix.$random;
    }
}
