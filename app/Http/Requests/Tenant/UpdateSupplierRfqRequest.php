<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierRfq;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierRfq $rfq */
        $rfq = $this->route('supplier_rfq');

        return $this->user()?->can('update', $rfq) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string'],
            'closes_at' => ['sometimes', 'nullable', 'date'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rfqData(): array
    {
        return $this->validated();
    }
}
