<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Activity;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Activity $resource
 *
 * @mixin Activity
 */
#[SchemaName('Activity')]
class ActivityResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer_type' => $this->causer_type,
            'causer_id' => $this->causer_id,
            'properties' => $this->properties,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
