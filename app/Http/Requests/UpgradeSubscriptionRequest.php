<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpgradeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() || $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'plan_name' => ['required', 'string', 'in:FREE,STARTER,BUSINESS,PRO'],
        ];
    }
}
