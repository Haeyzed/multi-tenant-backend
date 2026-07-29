<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\CrmActivityType;
use App\Models\Tenant\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CrmActivity $crmActivity */
        $crmActivity = $this->route('crm_activity');

        return $this->user()?->can('update', $crmActivity) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(CrmActivityType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{
     *     type?: string,
     *     subject?: string|null,
     *     body?: string|null,
     *     due_at?: string|null,
     *     completed_at?: string|null
     * }
     */
    public function activityData(): array
    {
        return $this->validated();
    }
}
