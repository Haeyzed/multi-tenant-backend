<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Tenant;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * Central API representation of a provisioned tenant.
 *
 * Includes optional nested domains when the `domains` relation is loaded.
 *
 * @property-read Tenant $resource
 *
 * @mixin Tenant
 */
#[SchemaName('Tenant')]
class TenantResource extends Resource
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     domains: mixed,
     *     created_at: mixed,
     *     updated_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Tenant UUID primary key.
             *
             * @format uuid
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'id' => $this->id,
            /**
             * Human-readable tenant display name.
             *
             * @example Acme Corp
             */
            'name' => $this->name,
            /**
             * Domains attached to the tenant (present when `domains` is included/loaded).
             *
             * @var DomainResource[]
             */
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            /**
             * @format date-time
             */
            'created_at' => $this->created_at,
            /**
             * @format date-time
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
