<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PurchaseRequestStatus;
use App\Events\Tenant\Erp\PurchaseRequestApproved;
use App\Models\Central\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseRequest;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Purchase request lifecycle and conversion to purchase orders.
 */
final class PurchaseRequestService
{
    public function __construct(private PurchaseOrderService $purchaseOrders) {}

    /**
     * @return LengthAwarePaginator<int, PurchaseRequest>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(PurchaseRequest::class)
            ->with(['requester', 'warehouse'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('requested_by'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     warehouse_id?: int|null,
     *     notes?: string|null,
     *     items: list<array{product_id: int, quantity: int, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): PurchaseRequest
    {
        $this->assertWarehouse($data['warehouse_id'] ?? null);

        return DB::transaction(function () use ($data): PurchaseRequest {
            /** @var PurchaseRequest $purchaseRequest */
            $purchaseRequest = PurchaseRequest::query()->create([
                'number' => 'PR-'.Str::upper(Str::random(10)),
                'status' => PurchaseRequestStatus::Draft,
                'requested_by' => auth()->id(),
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($purchaseRequest, $data['items']);

            return $this->find($purchaseRequest->refresh());
        });
    }

    /**
     * Load the purchase request with its related requester, approver, warehouse, order, and items.
     */
    public function find(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        return $purchaseRequest->loadMissing([
            'requester',
            'approver',
            'warehouse',
            'purchaseOrder',
            'items.product',
        ]);
    }

    /**
     * @param  array{
     *     warehouse_id?: int|null,
     *     notes?: string|null,
     *     items?: list<array{product_id: int, quantity: int, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(PurchaseRequest $purchaseRequest, array $data): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, PurchaseRequestStatus::Draft);

        if (array_key_exists('warehouse_id', $data)) {
            $this->assertWarehouse($data['warehouse_id']);
        }

        return DB::transaction(function () use ($purchaseRequest, $data): PurchaseRequest {
            if (array_key_exists('warehouse_id', $data)) {
                $purchaseRequest->warehouse_id = $data['warehouse_id'];
            }

            if (array_key_exists('notes', $data)) {
                $purchaseRequest->notes = $data['notes'];
            }

            if (isset($data['items'])) {
                $purchaseRequest->items()->delete();
                $this->syncItems($purchaseRequest, $data['items']);
            }

            $purchaseRequest->save();

            return $this->find($purchaseRequest->refresh());
        });
    }

    /**
     * Delete a draft purchase request.
     *
     * @throws ValidationException if the purchase request is not in draft status
     */
    public function delete(PurchaseRequest $purchaseRequest): void
    {
        $this->assertStatus($purchaseRequest, PurchaseRequestStatus::Draft);
        $purchaseRequest->delete();
    }

    /**
     * Submit a draft purchase request for approval.
     *
     * @throws ValidationException if the purchase request is not draft or has no items
     */
    public function submit(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, PurchaseRequestStatus::Draft);
        $this->assertHasItems($purchaseRequest);

        $purchaseRequest->update([
            'status' => PurchaseRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return $this->find($purchaseRequest->refresh());
    }

    /**
     * Approve a submitted purchase request and dispatch the approved event.
     *
     * @throws ValidationException if the purchase request is not in submitted status
     */
    public function approve(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, PurchaseRequestStatus::Submitted);

        $purchaseRequest->update([
            'status' => PurchaseRequestStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        /** @var Tenant $tenant */
        $tenant = tenant();
        event(new PurchaseRequestApproved($purchaseRequest->refresh(), (string) $tenant->getTenantKey()));

        return $this->find($purchaseRequest->refresh());
    }

    /**
     * Reject a submitted purchase request.
     *
     * @throws ValidationException if the purchase request is not in submitted status
     */
    public function reject(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, PurchaseRequestStatus::Submitted);

        $purchaseRequest->update([
            'status' => PurchaseRequestStatus::Rejected,
        ]);

        return $this->find($purchaseRequest->refresh());
    }

    /**
     * @throws Throwable
     */
    public function convertToPurchaseOrder(PurchaseRequest $purchaseRequest, int $supplierId): PurchaseOrder
    {
        $this->assertStatus($purchaseRequest, PurchaseRequestStatus::Approved);

        return DB::transaction(function () use ($purchaseRequest, $supplierId): PurchaseOrder {
            $purchaseRequest->loadMissing('items');

            $items = $purchaseRequest->items
                ->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])
                ->all();

            $purchaseOrder = $this->purchaseOrders->create([
                'supplier_id' => $supplierId,
                'warehouse_id' => $purchaseRequest->warehouse_id,
                'notes' => $purchaseRequest->notes,
                'items' => $items,
            ]);

            $purchaseRequest->update([
                'status' => PurchaseRequestStatus::Converted,
                'purchase_order_id' => $purchaseOrder->id,
                'converted_at' => now(),
            ]);

            return $this->purchaseOrders->find($purchaseOrder);
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: int, notes?: string|null}>  $items
     */
    private function syncItems(PurchaseRequest $purchaseRequest, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one purchase request item is required.'],
            ]);
        }

        foreach ($items as $index => $item) {
            /** @var Product|null $product */
            $product = Product::query()->find($item['product_id']);

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['The selected product is invalid.'],
                ]);
            }

            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            $purchaseRequest->items()->create([
                'product_id' => $product->id,
                'quantity' => (int) $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * Ensure the given warehouse exists, if provided.
     *
     * @throws ValidationException if the warehouse is invalid
     */
    private function assertWarehouse(?int $warehouseId): void
    {
        if ($warehouseId === null) {
            return;
        }

        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is invalid.'],
            ]);
        }
    }

    /**
     * Ensure the purchase request has at least one item.
     *
     * @throws ValidationException if the purchase request has no items
     */
    private function assertHasItems(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Purchase request must have at least one item.'],
            ]);
        }
    }

    /**
     * Ensure the purchase request is in the expected status.
     *
     * @throws ValidationException if the purchase request status does not match
     */
    private function assertStatus(PurchaseRequest $purchaseRequest, PurchaseRequestStatus $expected): void
    {
        if ($purchaseRequest->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Purchase request must be in {$expected->value} status."],
            ]);
        }
    }
}
