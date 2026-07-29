<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\FulfilmentStatus;
use App\Enums\Tenant\OrderStatus;
use App\Events\Tenant\Erp\OrderFulfilled;
use App\Models\Tenant;
use App\Models\Tenant\Fulfilment;
use App\Models\Tenant\FulfilmentItem;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Order fulfilment workflow.
 */
final class FulfilmentService
{
    /**
     * @return LengthAwarePaginator<int, Fulfilment>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Fulfilment::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('order_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('order'),
                AllowedInclude::relationship('items'),
                AllowedInclude::relationship('warehouse'),
            )
            ->defaultSort('-created_at')
            ->with(['order', 'items.orderItem'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     order_id: int,
     *     warehouse_id?: int|null,
     *     notes?: string|null,
     *     items: list<array{order_item_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Fulfilment
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($data['order_id']);
        $this->assertItems($data['items'], $order);

        return DB::transaction(function () use ($data, $order): Fulfilment {
            /** @var Fulfilment $fulfilment */
            $fulfilment = Fulfilment::query()->create([
                'number' => 'FUL-'.Str::upper(Str::random(10)),
                'order_id' => $order->id,
                'warehouse_id' => $this->resolveWarehouseId($data['warehouse_id'] ?? $order->warehouse_id),
                'status' => FulfilmentStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                FulfilmentItem::query()->create([
                    'fulfilment_id' => $fulfilment->id,
                    'order_item_id' => $item['order_item_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $this->find($fulfilment->refresh());
        });
    }

    public function find(Fulfilment $fulfilment): Fulfilment
    {
        return $fulfilment->loadMissing(['order.items', 'items.orderItem.product', 'warehouse']);
    }

    /**
     * @throws Throwable
     */
    public function complete(Fulfilment $fulfilment): Fulfilment
    {
        $this->assertStatus($fulfilment, FulfilmentStatus::Draft);

        return DB::transaction(function () use ($fulfilment): Fulfilment {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $fulfilment->loadMissing(['order.items', 'items.orderItem']);
            $order = $fulfilment->order;

            if (
                $order->status !== OrderStatus::Confirmed
                && $order->status !== OrderStatus::PartiallyFulfilled
            ) {
                throw ValidationException::withMessages([
                    'order' => ['Fulfilments can only be completed for confirmed or partially fulfilled orders.'],
                ]);
            }

            if ($fulfilment->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Fulfilment must have at least one item.'],
                ]);
            }

            foreach ($fulfilment->items as $item) {
                /** @var OrderItem $orderItem */
                $orderItem = $item->orderItem;
                $remaining = $orderItem->quantity - $orderItem->quantity_fulfilled;

                if ($item->quantity < 1 || $item->quantity > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => ["Invalid fulfilment quantity for order item {$orderItem->id}. Remaining: {$remaining}."],
                    ]);
                }

                $orderItem->increment('quantity_fulfilled', $item->quantity);
            }

            $fulfilment->update([
                'status' => FulfilmentStatus::Completed,
                'completed_at' => now(),
            ]);

            $order->refresh()->load('items');
            $fullyFulfilled = $order->items->every(
                fn (OrderItem $item): bool => $item->quantity_fulfilled >= $item->quantity
            );

            $order->update([
                'status' => $fullyFulfilled ? OrderStatus::Fulfilled : OrderStatus::PartiallyFulfilled,
            ]);

            $fulfilment = $this->find($fulfilment->refresh());

            if ($fullyFulfilled) {
                event(new OrderFulfilled($order->refresh(), (string) $tenant->getTenantKey()));
            }

            return $fulfilment;
        });
    }

    public function cancel(Fulfilment $fulfilment): Fulfilment
    {
        $this->assertStatus($fulfilment, FulfilmentStatus::Draft);

        $fulfilment->update(['status' => FulfilmentStatus::Cancelled]);

        return $this->find($fulfilment->refresh());
    }

    public function delete(Fulfilment $fulfilment): void
    {
        $this->assertStatus($fulfilment, FulfilmentStatus::Draft);
        $fulfilment->delete();
    }

    /**
     * @param  list<array{order_item_id: int, quantity: int}>  $items
     */
    private function assertItems(array $items, Order $order): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one fulfilment item is required.'],
            ]);
        }

        $order->loadMissing('items');
        $orderItemIds = $order->items->pluck('id')->all();

        foreach ($items as $index => $item) {
            if (! in_array($item['order_item_id'], $orderItemIds, true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.order_item_id" => ['The selected order item does not belong to this order.'],
                ]);
            }

            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }
        }
    }

    private function resolveWarehouseId(?int $warehouseId): ?int
    {
        if ($warehouseId !== null) {
            Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();

            return $warehouseId;
        }

        return null;
    }

    private function assertStatus(Fulfilment $fulfilment, FulfilmentStatus $expected): void
    {
        if ($fulfilment->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Fulfilment must be in {$expected->value} status."],
            ]);
        }
    }
}
