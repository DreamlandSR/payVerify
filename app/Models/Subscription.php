<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'plan_name',
        'max_verifications_per_month',
        'current_month_usage',
        'period_starts_at',
        'period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }
}
