<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\Domain;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * Central API representation of a tenant domain hostname.
 *
 * @property-read Domain $resource
 *
 * @mixin Domain
 */
#[SchemaName('Domain')]
class DomainResource extends Resource
{
    /**
     * @return array{
     *     id: int,
     *     domain: string,
     *     tenant_id: string,
     *     created_at: mixed,
     *     updated_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Domain row primary key.
             *
             * @example 1
             */
            'id' => $this->id,
            /**
             * Hostname used for tenant identification.
             *
             * @example acme.example.test
             */
            'domain' => $this->domain,
            /**
             * Owning tenant UUID.
             *
             * @format uuid
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'tenant_id' => $this->tenant_id,
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
