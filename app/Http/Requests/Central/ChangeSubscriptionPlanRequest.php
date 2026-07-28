<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ChangeSubscriptionPlanRequest extends FormRequest
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
        ];
    }

    /**
     * @return array{plan_price_id: int}
     */
    public function planChangeData(): array
    {
        /** @var array{plan_price_id: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
