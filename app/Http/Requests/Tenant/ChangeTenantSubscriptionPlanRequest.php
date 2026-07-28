<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\PlanPrice;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ChangeTenantSubscriptionPlanRequest extends FormRequest
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
            'plan_price_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! PlanPrice::query()->whereKey($value)->where('is_active', true)->exists()) {
                        $fail('The selected plan price id is invalid.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array{plan_price_id: int}
     */
    public function planChangeData(): array
    {
        return $this->validated();
    }
}
