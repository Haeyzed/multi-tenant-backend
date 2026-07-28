<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\CustomerNote;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read CustomerNote $resource
 *
 * @mixin CustomerNote
 */
#[SchemaName('CustomerNote')]
class CustomerNoteResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'subject' => $this->subject,
            'body' => $this->body,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
