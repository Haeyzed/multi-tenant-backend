<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;

class IndexCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CouponsView->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.code' => ['sometimes', 'string'],
            'filter.type' => ['sometimes', 'string'],
            'filter.duration' => ['sometimes', 'string'],
            'filter.is_active' => ['sometimes', 'boolean'],
            'filter.currency' => ['sometimes', 'string', 'size:3'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
