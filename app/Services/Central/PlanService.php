<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\PlanInterval;
use App\Models\Central\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Central plan catalog management.
 */
final class PlanService
{
    /**
     * @return LengthAwarePaginator<int, Plan>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Plan::class)
            ->allowedFilters(
                AllowedFilter::exact('slug'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('sort_order'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('sort_order')
            ->with(['prices', 'features'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Active catalog for tenant self-serve checkout.
     *
     * @return LengthAwarePaginator<int, Plan>
     */
    public function listActive(int $perPage = 50): LengthAwarePaginator
    {
        return QueryBuilder::for(Plan::query()->where('is_active', true))
            ->allowedFilters(
                AllowedFilter::exact('slug'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('sort_order'),
            )
            ->defaultSort('sort_order')
            ->with([
                'prices' => fn ($query) => $query->where('is_active', true),
                'features',
            ])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     name: string,
     *     slug?: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     trial_days?: int,
     *     sort_order?: int,
     *     prices?: list<array{currency: string, amount: int, interval: string, interval_count?: int, gateway_price_id?: string|null, is_active?: bool}>,
     *     features?: list<array{feature_key: string, value: string}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data): Plan {
            /** @var Plan $plan */
            $plan = Plan::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'trial_days' => $data['trial_days'] ?? 0,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncPrices($plan, $data['prices'] ?? []);
            $this->syncFeatures($plan, $data['features'] ?? []);

            return $plan->load(['prices', 'features']);
        });
    }

    public function find(Plan $plan): Plan
    {
        return $plan->loadMissing(['prices', 'features']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     slug?: string,
     *     description?: string|null,
     *     is_active?: bool,
     *     trial_days?: int,
     *     sort_order?: int,
     *     prices?: list<array{currency: string, amount: int, interval: string, interval_count?: int, gateway_price_id?: string|null, is_active?: bool}>,
     *     features?: list<array{feature_key: string, value: string}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data): Plan {
            $plan->fill(collect($data)->only([
                'name',
                'slug',
                'description',
                'is_active',
                'trial_days',
                'sort_order',
            ])->all())->save();

            if (array_key_exists('prices', $data)) {
                $plan->prices()->delete();
                $this->syncPrices($plan, $data['prices'] ?? []);
            }

            if (array_key_exists('features', $data)) {
                $plan->features()->delete();
                $this->syncFeatures($plan, $data['features'] ?? []);
            }

            return $plan->refresh()->load(['prices', 'features']);
        });
    }

    public function delete(Plan $plan): void
    {
        $plan->delete();
    }

    /**
     * @param  list<array{currency: string, amount: int, interval: string, interval_count?: int, gateway_price_id?: string|null, is_active?: bool}>  $prices
     */
    private function syncPrices(Plan $plan, array $prices): void
    {
        foreach ($prices as $price) {
            $plan->prices()->create([
                'currency' => strtoupper($price['currency']),
                'amount' => $price['amount'],
                'interval' => PlanInterval::from($price['interval']),
                'interval_count' => $price['interval_count'] ?? 1,
                'gateway_price_id' => $price['gateway_price_id'] ?? null,
                'is_active' => $price['is_active'] ?? true,
            ]);
        }
    }

    /**
     * @param  list<array{feature_key: string, value: string}>  $features
     */
    private function syncFeatures(Plan $plan, array $features): void
    {
        foreach ($features as $feature) {
            $plan->features()->create([
                'feature_key' => $feature['feature_key'],
                'value' => $feature['value'],
            ]);
        }
    }
}
