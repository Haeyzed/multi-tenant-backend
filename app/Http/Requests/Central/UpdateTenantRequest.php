<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for updating an existing tenant.
 *
 * Authorization requires the `update` ability on the route-bound tenant.
 * Both fields are optional; when present they must satisfy the same constraints
 * as provisioning (unique domain, ignoring the tenant's current domain row).
 */
class UpdateTenantRequest extends FormRequest
{
    /**
     * Only callers who may update the target tenant may use this endpoint.
     */
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()?->can('update', $tenant) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');
        $domainId = $tenant->domains()->value('id');

        return [
            /**
             * Updated tenant display name.
             *
             * @example Acme Holdings
             */
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            /**
             * Updated primary hostname (unique among domains, excluding the current one).
             *
             * @example acme-new.example.test
             */
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'lowercase',
                Rule::unique('domains', 'domain')->ignore($domainId),
            ],
        ];
    }

    /**
     * Validated tenant update attributes (only present keys).
     *
     * @return array{name?: string, domain?: string}
     */
    public function tenantData(): array
    {
        /** @var array{name?: string, domain?: string} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
