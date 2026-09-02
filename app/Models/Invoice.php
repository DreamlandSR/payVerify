<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'invoice_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'amount',
        'currency',
        'status',
        'due_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Generate a unique invoice number for the business tenant.
     */
    public static function generateInvoiceNumber(int $businessId): string
    {
        $prefix = 'INV-'.date('Ymd').'-';
        $random = Str::upper(Str::random(4));

        while (static::where('invoice_number', $prefix.$random)->exists()) {
            $random = Str::upper(Str::random(4));
        }

        return $prefix.$random;
    }
}
