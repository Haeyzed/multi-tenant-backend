<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Tax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tax $tax */
        $tax = $this->route('tax');

        return $this->user()?->can('update', $tax) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Tax $tax */
        $tax = $this->route('tax');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('taxes', 'code')->ignore($tax)],
            'rate_bps' => ['sometimes', 'integer', 'min:0'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, code?: string, rate_bps?: int, is_inclusive?: bool, is_default?: bool, is_active?: bool}
     */
    public function taxData(): array
    {
        return $this->validated();
    }
}
