<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WarehouseZone::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('warehouse_zones', 'code')->where('warehouse_id', $warehouse->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }

    /**
     * @return array{name: string, code: string, sort_order?: int, is_active?: bool}
     */
    public function zoneData(): array
    {
        /** @var array{name: string, code: string, sort_order?: int, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
