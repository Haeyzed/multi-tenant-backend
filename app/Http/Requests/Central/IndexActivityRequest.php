<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;

class IndexActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ActivityView->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.log_name' => ['sometimes', 'string'],
            'filter.event' => ['sometimes', 'string'],
            'filter.subject_type' => ['sometimes', 'string'],
            'filter.subject_id' => ['sometimes'],
            'filter.causer_type' => ['sometimes', 'string'],
            'filter.causer_id' => ['sometimes', 'integer'],
            'filter.description' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 25);
    }
}
