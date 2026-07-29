<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ApprovalRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApprovalRequest::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'approvable_type' => ['required', 'string'],
            'approvable_id' => ['required', 'integer'],
            'request_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{type: string, approvable_type: string, approvable_id: int, request_notes?: string|null}
     */
    public function approvalRequestData(): array
    {
        return $this->validated();
    }
}
