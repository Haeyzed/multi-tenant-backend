<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;

class IndexPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::PlansView->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
