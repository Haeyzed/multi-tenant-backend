<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PosSession;
use Illuminate\Foundation\Http\FormRequest;

class ClosePosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PosSession $posSession */
        $posSession = $this->route('pos_session');

        return $this->user()?->can('update', $posSession) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'closing_float' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{closing_float?: int|null, notes?: string|null}
     */
    public function closeData(): array
    {
        /** @var array{closing_float?: int|null, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
