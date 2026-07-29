<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\ReturnDisposition;
use App\Models\Tenant\ReturnAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectReturnAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ReturnAuthorization $returnAuthorization */
        $returnAuthorization = $this->route('return_authorization');

        return $this->user()?->can('inspect', $returnAuthorization) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'disposition' => ['required', Rule::enum(ReturnDisposition::class)],
            'inspection_notes' => ['nullable', 'string'],
        ];
    }
}
