<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\PlanInterval;
use App\Enums\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::PlansCreate->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'prices' => ['sometimes', 'array'],
            'prices.*.currency' => ['required_with:prices', 'string', 'size:3'],
            'prices.*.amount' => ['required_with:prices', 'integer', 'min:0'],
            'prices.*.interval' => ['required_with:prices', Rule::enum(PlanInterval::class)],
            'prices.*.interval_count' => ['sometimes', 'integer', 'min:1'],
            'prices.*.gateway_price_id' => ['nullable', 'string', 'max:255'],
            'prices.*.is_active' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*.feature_key' => ['required_with:features', 'string', 'max:255'],
            'features.*.value' => ['required_with:features', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planData(): array
    {
        $validated = $this->validated();

        if (isset($validated['prices'])) {
            $validated['prices'] = array_map(static function (array $price): array {
                $price['currency'] = strtoupper($price['currency']);
                $price['interval'] = $price['interval'] instanceof PlanInterval
                    ? $price['interval']->value
                    : (string) $price['interval'];

                return $price;
            }, $validated['prices']);
        }

        return $validated;
    }
}
