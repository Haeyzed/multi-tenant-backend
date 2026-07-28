<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WarehouseZone $zone */
        $zone = $this->route('zone');

        return $this->user()?->can('update', $zone) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');
        /** @var WarehouseZone $zone */
        $zone = $this->route('zone');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('warehouse_zones', 'code')
                    ->where('warehouse_id', $warehouse->id)
                    ->ignore($zone),
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
     * @return array{name?: string, code?: string, sort_order?: int, is_active?: bool}
     */
    public function zoneData(): array
    {
        return $this->validated();
    }
}
