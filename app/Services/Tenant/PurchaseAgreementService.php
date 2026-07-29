<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PurchaseAgreementStatus;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseAgreement;
use App\Models\Tenant\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Supplier purchase agreement lifecycle.
 */
final class PurchaseAgreementService
{
    /**
     * @return LengthAwarePaginator<int, PurchaseAgreement>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(PurchaseAgreement::class)
            ->with(['supplier', 'items.product'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('number'),
                AllowedFilter::partial('title'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('title'),
                AllowedSort::field('status'),
                AllowedSort::field('starts_at'),
                AllowedSort::field('ends_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     supplier_id: int,
     *     title: string,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     currency?: string,
     *     payment_terms?: string|null,
     *     notes?: string|null,
     *     items: list<array{
     *         product_id: int,
     *         unit_cost: int,
     *         currency?: string,
     *         min_order_qty?: int,
     *         lead_time_days?: int|null
     *     }>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): PurchaseAgreement
    {
        return DB::transaction(function () use ($data): PurchaseAgreement {
            Supplier::query()->findOrFail($data['supplier_id']);

            /** @var PurchaseAgreement $agreement */
            $agreement = PurchaseAgreement::query()->create([
                'supplier_id' => $data['supplier_id'],
                'number' => 'PA-'.Str::upper(Str::random(10)),
                'title' => $data['title'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'USD'),
                'payment_terms' => $data['payment_terms'] ?? null,
                'status' => PurchaseAgreementStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($agreement, $data['items'] ?? []);

            return $this->find($agreement->refresh());
        });
    }

    /**
     * Load a purchase agreement with its supplier and item/product relations.
     */
    public function find(PurchaseAgreement $agreement): PurchaseAgreement
    {
        return $agreement->loadMissing(['supplier', 'items.product']);
    }

    /**
     * @param  array{
     *     title?: string,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     currency?: string,
     *     payment_terms?: string|null,
     *     notes?: string|null,
     *     items?: list<array{
     *         product_id: int,
     *         unit_cost: int,
     *         currency?: string,
     *         min_order_qty?: int,
     *         lead_time_days?: int|null
     *     }>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(PurchaseAgreement $agreement, array $data): PurchaseAgreement
    {
        return DB::transaction(function () use ($agreement, $data): PurchaseAgreement {
            if ($agreement->status === PurchaseAgreementStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'agreement' => ['Cancelled agreements cannot be updated.'],
                ]);
            }

            if (isset($data['title'])) {
                $agreement->title = $data['title'];
            }

            if (array_key_exists('starts_at', $data)) {
                $agreement->starts_at = $data['starts_at'];
            }

            if (array_key_exists('ends_at', $data)) {
                $agreement->ends_at = $data['ends_at'];
            }

            if (isset($data['currency'])) {
                $agreement->currency = strtoupper($data['currency']);
            }

            if (array_key_exists('payment_terms', $data)) {
                $agreement->payment_terms = $data['payment_terms'];
            }

            if (array_key_exists('notes', $data)) {
                $agreement->notes = $data['notes'];
            }

            $agreement->save();

            if (isset($data['items'])) {
                $this->syncItems($agreement, $data['items']);
            }

            return $this->find($agreement->refresh());
        });
    }

    /**
     * Delete a purchase agreement, provided it is not currently active.
     *
     * @throws ValidationException if the agreement is active
     */
    public function delete(PurchaseAgreement $agreement): void
    {
        if ($agreement->status === PurchaseAgreementStatus::Active) {
            throw ValidationException::withMessages([
                'agreement' => ['Active agreements must be cancelled before deletion.'],
            ]);
        }

        $agreement->delete();
    }

    /**
     * Activate a draft or expired purchase agreement that has at least one item.
     *
     * @throws ValidationException if the agreement is not draft/expired or has no items
     * @throws Throwable
     */
    public function activate(PurchaseAgreement $agreement): PurchaseAgreement
    {
        return DB::transaction(function () use ($agreement): PurchaseAgreement {
            if ($agreement->status !== PurchaseAgreementStatus::Draft && $agreement->status !== PurchaseAgreementStatus::Expired) {
                throw ValidationException::withMessages([
                    'agreement' => ['Only draft or expired agreements can be activated.'],
                ]);
            }

            if ($agreement->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'items' => ['An agreement must include at least one item before activation.'],
                ]);
            }

            $agreement->forceFill([
                'status' => PurchaseAgreementStatus::Active,
                'starts_at' => $agreement->starts_at ?? now(),
            ])->save();

            return $this->find($agreement->refresh());
        });
    }

    /**
     * Cancel a purchase agreement that is not already cancelled.
     *
     * @throws ValidationException if the agreement is already cancelled
     * @throws Throwable
     */
    public function cancel(PurchaseAgreement $agreement): PurchaseAgreement
    {
        return DB::transaction(function () use ($agreement): PurchaseAgreement {
            if ($agreement->status === PurchaseAgreementStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'agreement' => ['Agreement is already cancelled.'],
                ]);
            }

            $agreement->forceFill(['status' => PurchaseAgreementStatus::Cancelled])->save();

            return $this->find($agreement->refresh());
        });
    }

    /**
     * Replace a purchase agreement's items with the given item list.
     *
     * @param  list<array{
     *     product_id: int,
     *     unit_cost: int,
     *     currency?: string,
     *     min_order_qty?: int,
     *     lead_time_days?: int|null
     * }>  $items
     *
     * @throws ValidationException if the item list is empty
     */
    private function syncItems(PurchaseAgreement $agreement, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['An agreement must include at least one item.'],
            ]);
        }

        $agreement->items()->delete();

        foreach ($items as $item) {
            Product::query()->findOrFail($item['product_id']);

            $agreement->items()->create([
                'product_id' => $item['product_id'],
                'unit_cost' => $item['unit_cost'],
                'currency' => strtoupper($item['currency'] ?? $agreement->currency),
                'min_order_qty' => $item['min_order_qty'] ?? 1,
                'lead_time_days' => $item['lead_time_days'] ?? null,
            ]);
        }
    }
}
