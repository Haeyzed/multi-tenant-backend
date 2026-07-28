<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\CouponType;
use App\Enums\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CouponsCreate->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:coupons,code'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'duration' => ['required', Rule::enum(CouponDuration::class)],
            'duration_in_months' => ['nullable', 'integer', 'min:1', 'required_if:duration,'.CouponDuration::Repeating->value],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function couponData(): array
    {
        $validated = $this->validated();

        if (isset($validated['type']) && $validated['type'] instanceof CouponType) {
            $validated['type'] = $validated['type']->value;
        }

        if (isset($validated['duration']) && $validated['duration'] instanceof CouponDuration) {
            $validated['duration'] = $validated['duration']->value;
        }

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper((string) $validated['currency']);
        }

        return $validated;
    }
}
