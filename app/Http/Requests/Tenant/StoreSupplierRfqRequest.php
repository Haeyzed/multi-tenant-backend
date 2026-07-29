<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierRfq;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierRfq::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'notes' => ['nullable', 'string'],
            'closes_at' => ['nullable', 'date'],
            'items' => ['required_without:purchase_request_id', 'array', 'min:1'],
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
