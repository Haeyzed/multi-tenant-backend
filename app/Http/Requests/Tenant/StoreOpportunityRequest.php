<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\OpportunityStage;
use App\Enums\Tenant\OpportunityStatus;
use App\Models\Tenant\Opportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Opportunity::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'stage' => ['sometimes', Rule::enum(OpportunityStage::class)],
            'status' => ['sometimes', Rule::enum(OpportunityStatus::class)],
            'amount' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     lead_id?: int|null,
     *     customer_id?: int|null,
     *     owner_id?: int|null,
     *     stage?: string,
     *     status?: string,
     *     amount?: int,
     *     currency?: string,
     *     probability?: int,
     *     expected_close_at?: string|null,
     *     notes?: string|null
     * }
     */
    public function opportunityData(): array
    {
        return $this->validated();
    }
}
