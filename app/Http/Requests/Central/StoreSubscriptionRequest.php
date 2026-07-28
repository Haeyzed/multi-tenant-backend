<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Permission;
use App\Services\Billing\BillingGatewayManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SubscriptionsManage->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_price_id' => ['required', 'integer', 'exists:plan_prices,id'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'gateway' => ['sometimes', 'string', Rule::in(app(BillingGatewayManager::class)->enabledGateways())],
        ];
    }

    /**
     * @return array{plan_price_id: int, coupon_code?: string|null, gateway?: string|null}
     */
    public function subscriptionData(): array
    {
        return $this->validated();
    }
}
