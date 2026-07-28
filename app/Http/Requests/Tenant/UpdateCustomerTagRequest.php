<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\CustomerTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CustomersUpdate->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var CustomerTag $tag */
        $tag = $this->route('customer_tag');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('customer_tags', 'slug')->ignore($tag)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array{name?: string, slug?: string, color?: string|null}
     */
    public function tagData(): array
    {
        return $this->validated();
    }
}
