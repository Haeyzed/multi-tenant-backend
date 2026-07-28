<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates adding a hostname to a tenant.
 */
class StoreDomainRequest extends FormRequest
{
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
        return [
            /**
             * Hostname to attach (must be unique across all tenants).
             *
             * @example acme-app.example.test
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
     * @return array{domain: string}
     */
    public function domainData(): array
    {
        /** @var array{domain: string} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
