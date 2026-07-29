<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Channel;
use Illuminate\Foundation\Http\FormRequest;

class IndexChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Channel::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.type' => ['sometimes', 'string'],
            'filter.adapter' => ['sometimes', 'string'],
            'filter.is_active' => ['sometimes', 'boolean'],
            'filter.is_default' => ['sometimes', 'boolean'],
            'filter.name' => ['sometimes', 'string'],
            'filter.code' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
