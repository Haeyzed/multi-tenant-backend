<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\OpportunityStage;
use App\Enums\Tenant\OpportunityStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * CRM opportunity pipeline.
 */
final class OpportunityService
{
    /**
     * @return LengthAwarePaginator<int, Opportunity>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Opportunity::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('stage'),
                AllowedFilter::exact('lead_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('owner_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('amount'),
                AllowedSort::field('status'),
                AllowedSort::field('expected_close_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['lead', 'customer', 'owner'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     name: string,
     *     lead_id?: int|null,
     *     customer_id?: int|null,
     *     owner_id?: int|null,
     *     stage?: string,
     *     status?: string,
     *     amount?: int,
     *     currency?: string,
     *     probability?: int,
     *     expected_close_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data): Opportunity
    {
        if (isset($data['lead_id'])) {
            Lead::query()->findOrFail($data['lead_id']);
        }

        if (isset($data['customer_id'])) {
            Customer::query()->findOrFail($data['customer_id']);
        }

        if (isset($data['owner_id'])) {
            $this->assertUser($data['owner_id']);
        }

        return Opportunity::query()->create([
            'number' => 'OPP-'.Str::upper(Str::random(10)),
            'name' => $data['name'],
            'lead_id' => $data['lead_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? auth()->id(),
            'stage' => $data['stage'] ?? OpportunityStage::Qualification->value,
            'status' => $data['status'] ?? OpportunityStatus::Open->value,
            'amount' => $data['amount'] ?? 0,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'probability' => $data['probability'] ?? 0,
            'expected_close_at' => $data['expected_close_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['lead', 'customer', 'owner']);
    }

    public function find(Opportunity $opportunity): Opportunity
    {
        return $opportunity->loadMissing(['lead', 'customer', 'owner', 'activities']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     lead_id?: int|null,
     *     customer_id?: int|null,
     *     owner_id?: int|null,
     *     stage?: string,
     *     status?: string,
     *     amount?: int,
     *     currency?: string,
     *     probability?: int,
     *     expected_close_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        if ($opportunity->status !== OpportunityStatus::Open) {
            throw ValidationException::withMessages([
                'status' => ['Only open opportunities can be updated.'],
            ]);
        }

        if (isset($data['owner_id'])) {
            $this->assertUser($data['owner_id']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $opportunity->fill($data)->save();

        return $this->find($opportunity->refresh());
    }

    public function delete(Opportunity $opportunity): void
    {
        if ($opportunity->status !== OpportunityStatus::Open) {
            throw ValidationException::withMessages([
                'status' => ['Only open opportunities can be deleted.'],
            ]);
        }

        $opportunity->delete();
    }

    public function markWon(Opportunity $opportunity): Opportunity
    {
        $this->assertOpen($opportunity);

        $opportunity->update([
            'status' => OpportunityStatus::Won,
            'stage' => OpportunityStage::Closed,
            'probability' => 100,
            'closed_at' => now(),
        ]);

        return $this->find($opportunity->refresh());
    }

    public function markLost(Opportunity $opportunity): Opportunity
    {
        $this->assertOpen($opportunity);

        $opportunity->update([
            'status' => OpportunityStatus::Lost,
            'stage' => OpportunityStage::Closed,
            'probability' => 0,
            'closed_at' => now(),
        ]);

        return $this->find($opportunity->refresh());
    }

    private function assertOpen(Opportunity $opportunity): void
    {
        if ($opportunity->status !== OpportunityStatus::Open) {
            throw ValidationException::withMessages([
                'status' => ['Opportunity must be open.'],
            ]);
        }
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
