<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\LeadStatus;
use App\Models\Tenant\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'estimated_value' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     source?: string|null,
     *     status?: string,
     *     owner_id?: int|null,
     *     estimated_value?: int|null,
     *     currency?: string|null,
     *     notes?: string|null
     * }
     */
    public function leadData(): array
    {
        return $this->validated();
    }
}
