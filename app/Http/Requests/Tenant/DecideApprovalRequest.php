<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ApprovalRequest as ApprovalRequestModel;
use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ApprovalRequestModel $approvalRequest */
        $approvalRequest = $this->route('approval_request');

        $ability = $this->route()?->getActionMethod() === 'reject' ? 'reject' : 'approve';

        return $this->user()?->can($ability, $approvalRequest) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision_notes' => ['nullable', 'string'],
        ];
    }

    public function decisionNotes(): ?string
    {
        /** @var string|null $notes */
        $notes = $this->validated('decision_notes');

        return $notes;
    }
}
