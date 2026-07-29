<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\ChannelAdapterKey;
use App\Enums\Tenant\ChannelType;
use App\Models\Tenant\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Channel::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', 'unique:channels,code'],
            'type' => ['sometimes', Rule::enum(ChannelType::class)],
            'adapter' => ['sometimes', 'nullable', Rule::enum(ChannelAdapterKey::class)],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'config' => ['sometimes', 'nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name: string, code?: string, type?: string, adapter?: string|null, warehouse_id?: int|null, is_active?: bool, is_default?: bool, config?: array<string, mixed>|null, notes?: string|null}
     */
    public function channelData(): array
    {
        $validated = $this->validated();

        if (isset($validated['type']) && $validated['type'] instanceof ChannelType) {
            $validated['type'] = $validated['type']->value;
        }

        if (isset($validated['adapter']) && $validated['adapter'] instanceof ChannelAdapterKey) {
            $validated['adapter'] = $validated['adapter']->value;
        }

        return $validated;
    }
}
