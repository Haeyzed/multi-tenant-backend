<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('update', $customer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name: string, email?: string|null, phone?: string|null, title?: string|null, is_primary?: bool, notes?: string|null}
     */
    public function contactData(): array
    {
        /** @var array{name: string, email?: string|null, phone?: string|null, title?: string|null, is_primary?: bool, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
