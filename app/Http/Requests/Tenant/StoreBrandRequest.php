<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Brand;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:brands,slug'],
            'description' => ['nullable', 'string'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'banner_url' => ['nullable', 'string', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, slug?: string, description?: string|null, logo_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool}
     */
    public function brandData(): array
    {
        /** @var array{name: string, slug?: string, description?: string|null, logo_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
