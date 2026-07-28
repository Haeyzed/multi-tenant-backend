<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\CustomerNoteType;
use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('update', $customer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(CustomerNoteType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
        ];
    }

    /**
     * @return array{type?: string, subject?: string|null, body?: string}
     */
    public function noteData(): array
    {
        return $this->validated();
    }
}
