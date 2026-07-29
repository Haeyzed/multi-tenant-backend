<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\ReturnAuthorizationStatus;
use App\Enums\Tenant\ReturnDisposition;
use App\Enums\Tenant\StockMovementReason;
use App\Events\Tenant\Erp\ReturnApproved;
use App\Events\Tenant\Erp\ReturnReceived;
use App\Events\Tenant\Erp\ReturnRefunded;
use App\Events\Tenant\Erp\ReturnRequested;
use App\Models\Central\Tenant;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ReturnAuthorization;
use App\Models\Tenant\SalesInvoice;
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
 * Customer RMA lifecycle: request → approve → receive (restock) → refund (credit note).
 */
final class ReturnAuthorizationService
{
    public function __construct(
        private StockLedgerService $ledger,
        private CreditNoteService $creditNotes,
        private OrderService $orders,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ReturnAuthorization>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ReturnAuthorization::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('order_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
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
                AllowedInclude::relationship('customer'),
                AllowedInclude::relationship('warehouse'),
                AllowedInclude::relationship('items'),
                AllowedInclude::relationship('creditNote'),
                AllowedInclude::relationship('salesInvoice'),
            )
            ->defaultSort('-created_at')
            ->with(['order', 'customer', 'items.product'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     order_id: int,
     *     warehouse_id?: int|null,
     *     sales_invoice_id?: int|null,
     *     reason?: string|null,
     *     notes?: string|null,
     *     items: list<array{order_item_id?: int|null, product_id: int, quantity: int, unit_price?: int, restock?: bool}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): ReturnAuthorization
    {
        return DB::transaction(function () use ($data): ReturnAuthorization {
            /** @var Order $order */
            $order = Order::query()->with('salesInvoice')->findOrFail($data['order_id']);

            $warehouseId = $data['warehouse_id'] ?? $order->warehouse_id;
            if ($warehouseId !== null) {
                $this->assertWarehouse($warehouseId);
            }

            $salesInvoiceId = $data['sales_invoice_id'] ?? $order->salesInvoice?->id;
            if ($salesInvoiceId !== null) {
                $this->assertInvoiceBelongsToOrder($salesInvoiceId, $order->id);
            }

            $lines = $this->buildLines($order, $data['items']);

            /** @var ReturnAuthorization $rma */
            $rma = ReturnAuthorization::query()->create([
                'number' => 'RMA-'.Str::upper(Str::random(10)),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'warehouse_id' => $warehouseId,
                'sales_invoice_id' => $salesInvoiceId,
                'status' => ReturnAuthorizationStatus::Draft,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $rma->items()->create($line);
            }

            return $this->find($rma->refresh());
        });
    }

    public function find(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        return $returnAuthorization->loadMissing([
            'order',
            'customer',
            'warehouse',
            'salesInvoice',
            'creditNote',
            'items.product',
            'items.orderItem',
        ]);
    }

    /**
     * @param  array{
     *     warehouse_id?: int|null,
     *     sales_invoice_id?: int|null,
     *     reason?: string|null,
     *     notes?: string|null,
     *     items?: list<array{order_item_id?: int|null, product_id: int, quantity: int, unit_price?: int, restock?: bool}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(ReturnAuthorization $returnAuthorization, array $data): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Draft);

        return DB::transaction(function () use ($returnAuthorization, $data): ReturnAuthorization {
            if (array_key_exists('warehouse_id', $data)) {
                if ($data['warehouse_id'] !== null) {
                    $this->assertWarehouse($data['warehouse_id']);
                }
                $returnAuthorization->warehouse_id = $data['warehouse_id'];
            }

            if (array_key_exists('sales_invoice_id', $data)) {
                if ($data['sales_invoice_id'] !== null) {
                    $this->assertInvoiceBelongsToOrder($data['sales_invoice_id'], $returnAuthorization->order_id);
                }
                $returnAuthorization->sales_invoice_id = $data['sales_invoice_id'];
            }

            if (array_key_exists('reason', $data)) {
                $returnAuthorization->reason = $data['reason'];
            }

            if (array_key_exists('notes', $data)) {
                $returnAuthorization->notes = $data['notes'];
            }

            if (isset($data['items'])) {
                /** @var Order $order */
                $order = Order::query()->findOrFail($returnAuthorization->order_id);
                $returnAuthorization->items()->delete();
                foreach ($this->buildLines($order, $data['items']) as $line) {
                    $returnAuthorization->items()->create($line);
                }
            }

            $returnAuthorization->save();

            return $this->find($returnAuthorization->refresh());
        });
    }

    public function delete(ReturnAuthorization $returnAuthorization): void
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Draft);
        $returnAuthorization->delete();
    }

    /**
     * @throws Throwable
     */
    public function submit(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Draft);
        $this->assertHasItems($returnAuthorization);

        return DB::transaction(function () use ($returnAuthorization): ReturnAuthorization {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $returnAuthorization->update([
                'status' => ReturnAuthorizationStatus::Requested,
                'requested_at' => now(),
            ]);

            $returnAuthorization = $this->find($returnAuthorization->refresh());

            event(new ReturnRequested($returnAuthorization, (string) $tenant->getTenantKey()));

            return $returnAuthorization;
        });
    }

    /**
     * @throws Throwable
     */
    public function approve(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Requested);

        return DB::transaction(function () use ($returnAuthorization): ReturnAuthorization {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $returnAuthorization->update([
                'status' => ReturnAuthorizationStatus::Approved,
                'approved_at' => now(),
            ]);

            $returnAuthorization = $this->find($returnAuthorization->refresh());

            event(new ReturnApproved($returnAuthorization, (string) $tenant->getTenantKey()));

            return $returnAuthorization;
        });
    }

    /**
     * @throws Throwable
     */
    public function receive(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Approved);

        return DB::transaction(function () use ($returnAuthorization): ReturnAuthorization {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $returnAuthorization->loadMissing(['items.product', 'warehouse', 'order']);

            $warehouse = $returnAuthorization->warehouse
                ?? ($returnAuthorization->order->warehouse_id !== null
                    ? Warehouse::query()->find($returnAuthorization->order->warehouse_id)
                    : null);

            if ($warehouse === null) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['A warehouse is required to receive returned stock.'],
                ]);
            }

            if ($returnAuthorization->warehouse_id === null) {
                $returnAuthorization->warehouse_id = $warehouse->id;
            }

            foreach ($returnAuthorization->items as $item) {
                $item->update(['quantity_received' => $item->quantity]);

                if ($item->restock && $item->product->track_inventory) {
                    $this->ledger->move(
                        warehouse: $warehouse,
                        product: $item->product,
                        quantityDelta: $item->quantity,
                        reason: StockMovementReason::CustomerReturn,
                        reference: $returnAuthorization,
                        notes: "Customer return {$returnAuthorization->number}",
                    );
                }
            }

            $returnAuthorization->update([
                'status' => ReturnAuthorizationStatus::Received,
                'received_at' => now(),
            ]);

            $returnAuthorization = $this->find($returnAuthorization->refresh());

            event(new ReturnReceived($returnAuthorization, (string) $tenant->getTenantKey()));

            return $returnAuthorization;
        });
    }

    /**
     * Issue a credit note against the linked sales invoice and mark the RMA refunded.
     *
     * @throws Throwable
     */
    public function refund(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        if (! in_array($returnAuthorization->status, [
            ReturnAuthorizationStatus::Received,
            ReturnAuthorizationStatus::Inspected,
            ReturnAuthorizationStatus::Repaired,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Return must be received, inspected, or repaired to refund.'],
            ]);
        }

        return DB::transaction(function () use ($returnAuthorization): ReturnAuthorization {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $returnAuthorization->loadMissing(['items.product', 'order.salesInvoice']);

            $salesInvoiceId = $returnAuthorization->sales_invoice_id
                ?? $returnAuthorization->order->salesInvoice?->id;

            if ($salesInvoiceId === null) {
                throw ValidationException::withMessages([
                    'sales_invoice_id' => ['A sales invoice is required to refund a return.'],
                ]);
            }

            $creditNote = $this->creditNotes->create([
                'sales_invoice_id' => $salesInvoiceId,
                'reason' => $returnAuthorization->reason ?? "Return {$returnAuthorization->number}",
                'notes' => $returnAuthorization->notes,
                'items' => $returnAuthorization->items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'description' => $item->product->name ?? 'Returned item',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
            ]);

            $creditNote = $this->creditNotes->issue($creditNote);

            $returnAuthorization->update([
                'sales_invoice_id' => $salesInvoiceId,
                'credit_note_id' => $creditNote->id,
                'status' => ReturnAuthorizationStatus::Refunded,
                'refunded_at' => now(),
            ]);

            $returnAuthorization = $this->find($returnAuthorization->refresh());

            event(new ReturnRefunded($returnAuthorization, (string) $tenant->getTenantKey()));

            return $returnAuthorization;
        });
    }

    /**
     * @param  array{inspection_notes?: string|null, disposition: string}  $data
     *
     * @throws Throwable
     */
    public function inspect(ReturnAuthorization $returnAuthorization, array $data): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Received);

        $returnAuthorization->update([
            'status' => ReturnAuthorizationStatus::Inspected,
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'disposition' => $data['disposition'],
            'inspected_at' => now(),
            'inspected_by' => auth()->id(),
        ]);

        return $this->find($returnAuthorization->refresh());
    }

    /**
     * @throws Throwable
     */
    public function replace(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Inspected);

        return DB::transaction(function () use ($returnAuthorization): ReturnAuthorization {
            $returnAuthorization->loadMissing(['items', 'order']);

            $replacement = $this->orders->create([
                'customer_id' => $returnAuthorization->customer_id,
                'warehouse_id' => $returnAuthorization->warehouse_id ?? $returnAuthorization->order->warehouse_id,
                'status' => OrderStatus::Draft->value,
                'notes' => "Replacement for return {$returnAuthorization->number}",
                'items' => $returnAuthorization->items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->all(),
            ]);

            $returnAuthorization->update([
                'status' => ReturnAuthorizationStatus::Replaced,
                'disposition' => ReturnDisposition::Replace->value,
                'replacement_order_id' => $replacement->id,
            ]);

            return $this->find($returnAuthorization->refresh());
        });
    }

    public function repair(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        $this->assertStatus($returnAuthorization, ReturnAuthorizationStatus::Inspected);

        $returnAuthorization->update([
            'status' => ReturnAuthorizationStatus::Repaired,
            'disposition' => ReturnDisposition::Repair->value,
        ]);

        return $this->find($returnAuthorization->refresh());
    }

    public function cancel(ReturnAuthorization $returnAuthorization): ReturnAuthorization
    {
        if (! in_array($returnAuthorization->status, [
            ReturnAuthorizationStatus::Draft,
            ReturnAuthorizationStatus::Requested,
            ReturnAuthorizationStatus::Approved,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft, requested, or approved returns can be cancelled.'],
            ]);
        }

        $returnAuthorization->update([
            'status' => ReturnAuthorizationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $this->find($returnAuthorization->refresh());
    }

    /**
     * @param  list<array{order_item_id?: int|null, product_id: int, quantity: int, unit_price?: int, restock?: bool}>  $items
     * @return list<array{order_item_id: int|null, product_id: int, quantity: int, quantity_received: int, unit_price: int, line_total: int, restock: bool}>
     */
    private function buildLines(Order $order, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['A return must include at least one item.'],
            ]);
        }

        $lines = [];

        foreach ($items as $index => $item) {
            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            /** @var Product $product */
            $product = Product::query()->findOrFail($item['product_id']);

            $orderItem = null;
            if (isset($item['order_item_id'])) {
                /** @var OrderItem|null $orderItem */
                $orderItem = OrderItem::query()
                    ->whereKey($item['order_item_id'])
                    ->where('order_id', $order->id)
                    ->first();

                if ($orderItem === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.order_item_id" => ['The order item does not belong to this order.'],
                    ]);
                }

                if ($orderItem->product_id !== $product->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['The product does not match the order item.'],
                    ]);
                }

                if ($item['quantity'] > $orderItem->quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Return quantity cannot exceed the ordered quantity.'],
                    ]);
                }
            }

            $unitPrice = $item['unit_price'] ?? $orderItem?->unit_price ?? $product->unit_price;
            $quantity = $item['quantity'];

            $lines[] = [
                'order_item_id' => $orderItem?->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'quantity_received' => 0,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'restock' => $item['restock'] ?? true,
            ];
        }

        return $lines;
    }

    private function assertWarehouse(int $warehouseId): void
    {
        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is invalid.'],
            ]);
        }
    }

    private function assertInvoiceBelongsToOrder(int $salesInvoiceId, int $orderId): void
    {
        $exists = SalesInvoice::query()
            ->whereKey($salesInvoiceId)
            ->where('order_id', $orderId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'sales_invoice_id' => ['The sales invoice does not belong to this order.'],
            ]);
        }
    }

    private function assertHasItems(ReturnAuthorization $returnAuthorization): void
    {
        if ($returnAuthorization->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['A return must include at least one item.'],
            ]);
        }
    }

    private function assertStatus(ReturnAuthorization $returnAuthorization, ReturnAuthorizationStatus $expected): void
    {
        if ($returnAuthorization->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Return must be in {$expected->value} status."],
            ]);
        }
    }
}
