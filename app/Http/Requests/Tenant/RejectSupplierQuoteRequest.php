<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierQuote;
use Illuminate\Foundation\Http\FormRequest;

class RejectSupplierQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierQuote $quote */
        $quote = $this->route('supplier_quote');

        return $this->user()?->can('reject', $quote) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
