<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ReportsView->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'threshold' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}
