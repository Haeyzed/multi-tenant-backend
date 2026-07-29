<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\LeadStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * CRM lead capture and conversion.
 */
final class LeadService
{
    /**
     * @return LengthAwarePaginator<int, Lead>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Lead::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['owner', 'customer'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     name: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     source?: string|null,
     *     status?: string,
     *     owner_id?: int|null,
     *     estimated_value?: int|null,
     *     currency?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data): Lead
    {
        if (isset($data['owner_id'])) {
            $this->assertUser($data['owner_id']);
        }

        return Lead::query()->create([
            'number' => 'LEAD-'.Str::upper(Str::random(10)),
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'source' => $data['source'] ?? null,
            'status' => $data['status'] ?? LeadStatus::New->value,
            'owner_id' => $data['owner_id'] ?? auth()->id(),
            'estimated_value' => $data['estimated_value'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'notes' => $data['notes'] ?? null,
        ])->load(['owner', 'customer']);
    }

    public function find(Lead $lead): Lead
    {
        return $lead->loadMissing(['owner', 'customer', 'opportunities', 'activities']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     source?: string|null,
     *     status?: string,
     *     owner_id?: int|null,
     *     estimated_value?: int|null,
     *     currency?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(Lead $lead, array $data): Lead
    {
        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages([
                'status' => ['Converted leads cannot be updated.'],
            ]);
        }

        if (isset($data['owner_id'])) {
            $this->assertUser($data['owner_id']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $lead->fill($data)->save();

        return $this->find($lead->refresh());
    }

    public function delete(Lead $lead): void
    {
        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages([
                'status' => ['Converted leads cannot be deleted.'],
            ]);
        }

        $lead->delete();
    }

    /**
     * @throws Throwable
     */
    public function convert(Lead $lead, ?int $customerId = null): Lead
    {
        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages([
                'status' => ['Lead is already converted.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $customerId): Lead {
            if ($customerId !== null) {
                Customer::query()->findOrFail($customerId);
            } else {
                $customer = Customer::query()->create([
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company' => $lead->company,
                    'notes' => $lead->notes,
                    'is_active' => true,
                ]);
                $customerId = $customer->id;
            }

            $lead->update([
                'customer_id' => $customerId,
                'status' => LeadStatus::Converted,
                'converted_at' => now(),
            ]);

            return $this->find($lead->refresh());
        });
    }

    private function assertUser(int $userId): void
    {
        if (! User::query()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                'owner_id' => ['The selected owner is invalid.'],
            ]);
        }
    }
}
