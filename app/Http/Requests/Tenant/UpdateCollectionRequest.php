<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\CollectionType;
use App\Models\Tenant\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
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
        /** @var Collection $collection */
        $collection = $this->route('collection');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('collections', 'slug')->ignore($collection)],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::enum(CollectionType::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'rules' => ['sometimes', 'array'],
            'rules.*.field' => ['required_with:rules', 'string', Rule::in(['title', 'sku', 'type', 'status', 'brand_id', 'price'])],
            'rules.*.operator' => ['required_with:rules', 'string', Rule::in(['eq', 'neq', 'contains', 'gt', 'gte', 'lt', 'lte'])],
            'rules.*.value' => ['required_with:rules', 'string', 'max:255'],
            'rules.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collectionData(): array
    {
        return $this->validated();
    }
}
