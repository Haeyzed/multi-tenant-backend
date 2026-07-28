<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return $this->user()?->can('update', $brand) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($brand)],
            'description' => ['nullable', 'string'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'banner_url' => ['nullable', 'string', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, slug?: string, description?: string|null, logo_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool}
     */
    public function brandData(): array
    {
        return $this->validated();
    }
}
