<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PurchaseAgreement;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseAgreement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_terms' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.min_order_qty' => ['sometimes', 'integer', 'min:1'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function agreementData(): array
    {
        return $this->validated();
    }
}
