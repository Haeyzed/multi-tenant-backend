<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\PlanPrice;
use App\Services\Billing\BillingGatewayManager;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantSubscriptionRequest extends FormRequest
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
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'gateway' => ['sometimes', 'string', Rule::in(app(BillingGatewayManager::class)->enabledGateways())],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array{plan_price_id: int, coupon_code?: string|null, gateway?: string|null, customer_email?: string|null}
     */
    public function subscriptionData(): array
    {
        return $this->validated();
    }
}
