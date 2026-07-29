<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\CrmActivityType;
use App\Models\Tenant\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CrmActivity::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subjectable_type' => ['required', 'string'],
            'subjectable_id' => ['required', 'integer'],
            'type' => ['sometimes', Rule::enum(CrmActivityType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{
     *     subjectable_type: string,
     *     subjectable_id: int,
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
