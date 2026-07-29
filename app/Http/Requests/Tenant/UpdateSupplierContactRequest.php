<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierContactRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
