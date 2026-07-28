<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\User;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * Central API representation of a platform (central) user.
 *
 * @property-read User $resource
 *
 * @mixin User
 */
#[SchemaName('CentralUser')]
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
             * Central user primary key.
             *
             * @example 1
             */
            'id' => $this->id,
            /**
             * Display name.
             *
             * @example Platform Admin
             */
            'name' => $this->name,
            /**
             * Login email address.
             *
             * @format email
             *
             * @example admin@example.com
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
             * @example ["platform_admin"]
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
