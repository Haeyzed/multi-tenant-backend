<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ApprovalRequest;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ApprovalRequest $resource
 *
 * @mixin ApprovalRequest
 */
#[SchemaName('ApprovalRequest')]
class ApprovalRequestResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type,
            'approvable_type' => $this->approvable_type,
            'approvable_id' => $this->approvable_id,
            'status' => $this->status->value,
            'requested_by' => $this->requested_by,
            'decided_by' => $this->decided_by,
            'request_notes' => $this->request_notes,
            'decision_notes' => $this->decision_notes,
            'decided_at' => $this->decided_at,
            'requester' => UserResource::make($this->whenLoaded('requester')),
            'decider' => UserResource::make($this->whenLoaded('decider')),
            'approvable' => $this->whenLoaded('approvable'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
