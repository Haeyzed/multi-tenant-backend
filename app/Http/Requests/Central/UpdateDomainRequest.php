<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates updating a tenant hostname.
 */
class UpdateDomainRequest extends FormRequest
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
        /** @var Domain $domain */
        $domain = $this->route('domain');

        return [
            /**
             * Updated hostname (unique, excluding this domain row).
             *
             * @example acme-new.example.test
             */
            'domain' => [
                'required',
                'string',
                'max:255',
                'lowercase',
                Rule::unique('domains', 'domain')->ignore($domain->id),
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
