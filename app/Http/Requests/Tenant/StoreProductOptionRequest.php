<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'values' => ['sometimes', 'array'],
            'values.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array{name: string, position?: int, values?: list<string>}
     */
    public function optionData(): array
    {
        return $this->validated();
    }
}
