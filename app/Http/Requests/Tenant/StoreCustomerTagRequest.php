<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:customer_tags,slug'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array{name: string, slug?: string, color?: string|null}
     */
    public function tagData(): array
    {
        /** @var array{name: string, slug?: string, color?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
