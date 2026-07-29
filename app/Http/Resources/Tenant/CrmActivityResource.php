<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\CrmActivity;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read CrmActivity $resource
 *
 * @mixin CrmActivity
 */
#[SchemaName('CrmActivity')]
class CrmActivityResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'subject' => $this->subject,
            'body' => $this->body,
            'subjectable_type' => $this->subjectable_type,
            'subjectable_id' => $this->subjectable_id,
            'user_id' => $this->user_id,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'user' => UserResource::make($this->whenLoaded('user')),
            'subjectable' => $this->whenLoaded('subjectable'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
