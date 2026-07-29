<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class IndexWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WorkOrder::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.status' => ['sometimes', 'string'],
            'filter.warehouse_id' => ['sometimes', 'integer'],
            'filter.product_id' => ['sometimes', 'integer'],
            'filter.bill_of_material_id' => ['sometimes', 'integer'],
            'filter.number' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
