<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class ConvertPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseRequest $purchaseRequest */
        $purchaseRequest = $this->route('purchase_request');

        return $this->user()?->can('convert', $purchaseRequest) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ];
    }

    public function supplierId(): int
    {
        return (int) $this->integer('supplier_id');
    }
}
