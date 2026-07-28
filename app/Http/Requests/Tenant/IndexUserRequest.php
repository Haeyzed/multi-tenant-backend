<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query parameters for listing tenant users.
 *
 * Authorization requires the `viewAny` ability on {@see User}. Filter, sort,
 * and sparse field parameters follow Spatie Query Builder conventions.
 */
class IndexUserRequest extends FormRequest
{
    /**
     * Only callers who may list users may use this endpoint.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Spatie Query Builder filter bag. */
            'filter' => ['sometimes', 'array'],
            /**
             * Filter by user ID.
             *
             * @example 1
             */
            'filter.id' => ['sometimes', 'integer'],
            /**
             * Filter by user name.
             *
             * @example Jane
             */
            'filter.name' => ['sometimes', 'string'],
            /**
             * Filter by email address.
             *
             * @example jane@example.com
             */
            'filter.email' => ['sometimes', 'string'],
            /**
             * Comma-separated sort fields. Prefix with `-` for descending.
             *
             * @example -created_at,name
             */
            'sort' => ['sometimes', 'string'],
            /** Spatie Query Builder sparse fieldsets bag. */
            'fields' => ['sometimes', 'array'],
            /**
             * Comma-separated user attributes to return.
             *
             * @example id,name,email
             */
            'fields.users' => ['sometimes', 'string'],
            /**
             * Number of users per page.
             *
             * @example 15
             *
             * @default 15
             */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Resolved page size for the user listing query.
     */
    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
