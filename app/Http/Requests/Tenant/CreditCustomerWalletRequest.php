<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Customer;
use App\Policies\Tenant\CustomerWalletPolicy;
use Illuminate\Foundation\Http\FormRequest;

class CreditCustomerWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');
        $user = $this->user();

        return $user !== null
            && app(CustomerWalletPolicy::class)->credit($user, $customer);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{amount: int, notes?: string|null}
     */
    public function walletData(): array
    {
        /** @var array{amount: int, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
