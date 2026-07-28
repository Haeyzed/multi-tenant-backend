<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\CouponType;
use App\Enums\Central\Permission;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Coupon $coupon */
        $coupon = $this->route('coupon');

        return $this->user()?->can(Permission::CouponsUpdate->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->route('coupon');

        return [
            'code' => ['sometimes', 'string', 'max:64', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'type' => ['sometimes', Rule::enum(CouponType::class)],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'duration' => ['sometimes', Rule::enum(CouponDuration::class)],
            'duration_in_months' => ['nullable', 'integer', 'min:1'],
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

        if (array_key_exists('currency', $validated) && $validated['currency'] !== null) {
            $validated['currency'] = strtoupper((string) $validated['currency']);
        }

        return $validated;
    }
}
