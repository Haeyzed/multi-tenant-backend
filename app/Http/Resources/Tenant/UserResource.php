<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\User;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * Tenant API representation of a user in the current tenant database.
 *
 * Includes Spatie role names when the `roles` relation is loaded.
 *
 * @property-read User $resource
 *
 * @mixin User
 */
#[SchemaName('TenantUser')]
class UserResource extends Resource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     email_verified_at: mixed,
     *     roles?: list<string>,
     *     created_at: mixed,
     *     updated_at: mixed
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Tenant user primary key.
             *
             * @example 1
             */
            'id' => $this->id,
            /**
             * Display name.
             *
             * @example Jane Doe
             */
            'name' => $this->name,
            /**
             * Login email address unique within the tenant.
             *
             * @format email
             *
             * @example jane@example.com
             */
            'email' => $this->email,
            /**
             * Timestamp when the email was verified, or null if unverified.
             *
             * @format date-time
             */
            'email_verified_at' => $this->email_verified_at,
            /**
             * Assigned Spatie role names (present when `roles` is loaded).
             *
             * @var list<string>
             *
             * @example ["admin"]
             */
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
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
