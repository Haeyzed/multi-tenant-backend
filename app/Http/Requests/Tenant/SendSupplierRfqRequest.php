<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierRfq;
use Illuminate\Foundation\Http\FormRequest;

class SendSupplierRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierRfq $rfq */
        $rfq = $this->route('supplier_rfq');

        return $this->user()?->can('send', $rfq) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['required', 'integer', 'exists:suppliers,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function supplierIds(): array
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $this->validated('supplier_ids'));

        return $ids;
    }
}
