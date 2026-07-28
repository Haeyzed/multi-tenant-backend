<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Collection;
use Illuminate\Foundation\Http\FormRequest;

class SyncCollectionProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Collection $collection */
        $collection = $this->route('collection');

        return $this->user()?->can('update', $collection) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('product_ids');

        return $ids;
    }
}
