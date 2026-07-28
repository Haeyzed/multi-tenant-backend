<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseBin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseBinRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WarehouseBin $bin */
        $bin = $this->route('bin');

        return $this->user()?->can('update', $bin) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');
        /** @var WarehouseBin $bin */
        $bin = $this->route('bin');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('warehouse_bins', 'code')
                    ->where('warehouse_id', $warehouse->id)
                    ->ignore($bin),
            ],
            'warehouse_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouse_zones', 'id')->where('warehouse_id', $warehouse->id),
            ],
            'aisle' => ['nullable', 'string', 'max:64'],
            'rack' => ['nullable', 'string', 'max:64'],
            'shelf' => ['nullable', 'string', 'max:64'],
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
     * @return array{name?: string, code?: string, warehouse_zone_id?: int|null, aisle?: string|null, rack?: string|null, shelf?: string|null, is_active?: bool}
     */
    public function binData(): array
    {
        return $this->validated();
    }
}
