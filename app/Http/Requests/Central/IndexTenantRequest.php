<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query parameters for listing tenants.
 *
 * Authorization requires the `viewAny` ability on {@see Tenant}. Filter, sort,
 * and include parameters follow Spatie Query Builder conventions.
 */
class IndexTenantRequest extends FormRequest
{
    /**
     * Only callers who may list tenants may use this endpoint.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Tenant::class) ?? false;
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
             * Filter by tenant UUID.
             *
             * @example 550e8400-e29b-41d4-a716-446655440000
             */
            'filter.id' => ['sometimes', 'string'],
            /**
             * Filter by tenant display name (partial match depending on query builder config).
             *
             * @example Acme
             */
            'filter.name' => ['sometimes', 'string'],
            /**
             * Filter by primary domain hostname.
             *
             * @example acme.example.test
             */
            'filter.domain' => ['sometimes', 'string'],
            /**
             * Comma-separated sort fields. Prefix with `-` for descending.
             *
             * @example -created_at,name
             */
            'sort' => ['sometimes', 'string'],
            /**
             * Comma-separated relations to include (for example `domains`).
             *
             * @example domains
             */
            'include' => ['sometimes', 'string'],
            /**
             * Number of tenants per page.
             *
             * @example 15
             *
             * @default 15
             */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Resolved page size for the tenant listing query.
     */
    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
