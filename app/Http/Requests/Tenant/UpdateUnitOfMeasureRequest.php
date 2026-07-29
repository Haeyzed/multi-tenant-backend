<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UnitOfMeasure $unitOfMeasure */
        $unitOfMeasure = $this->route('unit_of_measure');

        return $this->user()?->can('update', $unitOfMeasure) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var UnitOfMeasure $unitOfMeasure */
        $unitOfMeasure = $this->route('unit_of_measure');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('units_of_measure', 'code')->ignore($unitOfMeasure)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, code?: string, is_active?: bool}
     */
    public function unitOfMeasureData(): array
    {
        return $this->validated();
    }
}
