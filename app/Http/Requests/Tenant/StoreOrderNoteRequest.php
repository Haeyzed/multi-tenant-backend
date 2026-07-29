<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\OrderNoteType;
use App\Models\Tenant\OrderNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OrderNote::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(OrderNoteType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }

    /**
     * @return array{type?: string, subject?: string|null, body: string}
     */
    public function noteData(): array
    {
        $validated = $this->validated();

        if (isset($validated['type']) && $validated['type'] instanceof OrderNoteType) {
            $validated['type'] = $validated['type']->value;
        }

        return $validated;
    }
}
