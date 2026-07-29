<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ProductMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProductMedia $medium */
        $medium = $this->route('medium');

        return $this->user()?->can('update', $medium) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['image', 'document', 'video'])],
            'url' => ['sometimes', 'string', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{type?: string, url?: string, alt_text?: string|null, position?: int, is_primary?: bool}
     */
    public function mediaData(): array
    {
        return $this->validated();
    }
}
