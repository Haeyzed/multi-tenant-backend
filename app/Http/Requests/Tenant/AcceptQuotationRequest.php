<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Models\Tenant\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quotation $quotation */
        $quotation = $this->route('quotation');

        return $this->user()?->can('accept', $quotation) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in([OrderStatus::Confirmed->value, OrderStatus::Pending->value])],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{status?: string, tax_id?: int|null, warehouse_id?: int|null, notes?: string|null}
     */
    public function acceptData(): array
    {
        $validated = $this->validated();

        if (isset($validated['status']) && $validated['status'] instanceof OrderStatus) {
            $validated['status'] = $validated['status']->value;
        }

        return $validated;
    }
}
