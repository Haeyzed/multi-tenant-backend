<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\OrderNoteType;
use App\Models\Tenant\OrderNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OrderNote $note */
        $note = $this->route('note');

        return $this->user()?->can('update', $note) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(OrderNoteType::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
        ];
    }

    /**
     * @return array{type?: string, subject?: string|null, body?: string}
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
