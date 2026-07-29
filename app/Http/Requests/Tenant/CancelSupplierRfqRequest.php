<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierRfq;
use Illuminate\Foundation\Http\FormRequest;

class CancelSupplierRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierRfq $rfq */
        $rfq = $this->route('supplier_rfq');

        return $this->user()?->can('cancel', $rfq) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
