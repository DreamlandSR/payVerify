<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'payment_id',
        'file_path',
        'file_name',
        'file_hash',
        'file_size',
        'mime_type',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
