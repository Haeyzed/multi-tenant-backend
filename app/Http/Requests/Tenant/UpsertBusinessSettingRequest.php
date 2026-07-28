<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertBusinessSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsUpdate->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+$/'],
            'value' => ['nullable'],
            'type' => ['sometimes', Rule::in(['string', 'boolean', 'integer', 'json'])],
            'group' => ['sometimes', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{key: string, value?: mixed, type?: string, group?: string, description?: string|null}
     */
    public function settingData(): array
    {
        return $this->validated();
    }
}
