<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\CustomerAddressType;
use App\Models\Tenant\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Supplier $supplier */
        $supplier = $this->route('supplier');

        return $this->user()?->can('update', $supplier) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(CustomerAddressType::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
