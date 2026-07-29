<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PurchaseAgreement;
use Illuminate\Foundation\Http\FormRequest;

class CancelPurchaseAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PurchaseAgreement $agreement */
        $agreement = $this->route('purchase_agreement');

        return $this->user()?->can('cancel', $agreement) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
