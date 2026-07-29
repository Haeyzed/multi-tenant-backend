<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Customer;
use App\Policies\Tenant\CustomerWalletPolicy;
use Illuminate\Foundation\Http\FormRequest;

class EarnCustomerLoyaltyPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');
        $user = $this->user();

        return $user !== null
            && app(CustomerWalletPolicy::class)->updatePoints($user, $customer);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
