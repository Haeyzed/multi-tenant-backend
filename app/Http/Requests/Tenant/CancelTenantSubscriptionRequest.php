<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use Illuminate\Foundation\Http\FormRequest;

class CancelTenantSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::BillingManage->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'at_period_end' => ['sometimes', 'boolean'],
        ];
    }

    public function atPeriodEnd(): bool
    {
        return $this->boolean('at_period_end', true);
    }
}
