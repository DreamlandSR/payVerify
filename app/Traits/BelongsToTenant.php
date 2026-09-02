<?php

namespace App\Traits;

use App\Models\Business;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the tenant trait for a model.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->business_id && Auth::check() && Auth::user()->business_id) {
                $model->business_id = Auth::user()->business_id;
            }
        });
    }

    /**
     * Get the business tenant that owns the model.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
