<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\DataJob;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read DataJob $resource
 *
 * @mixin DataJob
 */
#[SchemaName('DataJob')]
class DataJobResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'resource' => $this->resource->value,
            'status' => $this->status->value,
            'options' => $this->options,
            'result' => $this->result,
            'error_message' => $this->error_message,
            'created_by' => $this->created_by,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
