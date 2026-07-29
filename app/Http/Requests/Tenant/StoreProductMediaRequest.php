<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ProductMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductMedia::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['image', 'document', 'video'])],
            'url' => ['required', 'string', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{type: string, url: string, alt_text?: string|null, position?: int, is_primary?: bool}
     */
    public function mediaData(): array
    {
        /** @var array{type: string, url: string, alt_text?: string|null, position?: int, is_primary?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
