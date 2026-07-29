<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for provisioning a new tenant.
 *
 * Authorization requires the `create` ability on {@see Tenant}. Creates the
 * central tenant record plus a unique primary domain hostname.
 */
class StoreTenantRequest extends FormRequest
{
    /**
     * Only callers who may create tenants may use this endpoint.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * Human-readable tenant display name.
             *
             * @example Acme Corp
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Primary hostname used to identify the tenant (must be unique).
             *
             * @example acme.example.test
             */
            'domain' => [
                'required',
                'string',
                'max:255',
                'lowercase',
                Rule::unique('domains', 'domain'),
            ],
        ];
    }

    /**
     * Validated tenant provisioning attributes.
     *
     * @return array{name: string, domain: string}
     */
    public function tenantData(): array
    {
        /** @var array{name: string, domain: string} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
