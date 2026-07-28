<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Collection;
use Illuminate\Foundation\Http\FormRequest;

class IndexCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Collection::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.name' => ['sometimes', 'string'],
            'filter.slug' => ['sometimes', 'string'],
            'filter.type' => ['sometimes', 'string'],
            'filter.is_featured' => ['sometimes', 'boolean'],
            'filter.is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
