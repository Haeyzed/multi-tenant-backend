<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Tax;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tax::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:taxes,code'],
            'rate_bps' => ['required', 'integer', 'min:0'],
            'is_inclusive' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, code: string, rate_bps: int, is_inclusive?: bool, is_default?: bool, is_active?: bool}
     */
    public function taxData(): array
    {
        /** @var array{name: string, code: string, rate_bps: int, is_inclusive?: bool, is_default?: bool, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
