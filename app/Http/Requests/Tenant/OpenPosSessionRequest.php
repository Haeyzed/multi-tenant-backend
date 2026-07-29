<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PosSession;
use Illuminate\Foundation\Http\FormRequest;

class OpenPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PosSession::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'opening_float' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{channel_id: int, opening_float?: int, notes?: string|null}
     */
    public function sessionData(): array
    {
        /** @var array{channel_id: int, opening_float?: int, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
