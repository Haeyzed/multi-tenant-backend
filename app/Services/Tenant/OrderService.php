<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\SalesInvoiceStatus;
use App\Enums\Tenant\SalesPaymentStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Events\Tenant\Erp\OrderConfirmed;
use App\Models\Tenant;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\SalesInvoice;
use App\Models\Tenant\SalesPaymentAllocation;
use App\Models\Tenant\Tax;
use App\Models\Tenant\Warehouse;
use App\Services\Billing\EntitlementEnforcer;
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
 * Tenant sales order management.
 */
final class OrderService
{
    public function __construct(
        private SalesInvoiceService $salesInvoices,
        private EntitlementEnforcer $entitlements,
        private WarehouseService $warehouses,
        private StockLedgerService $ledger,
        private ReservationService $reservations,
        private PricingEngine $pricing,
        private InventoryValuationStrategy $valuation,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Order::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('tax_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('channel_id'),
                AllowedFilter::exact('pos_session_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('total'),
                AllowedSort::field('placed_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('customer'),
                AllowedInclude::relationship('items'),
                AllowedInclude::relationship('salesInvoice'),
                AllowedInclude::relationship('taxRate'),
                AllowedInclude::relationship('warehouse'),
                AllowedInclude::relationship('channel'),
                AllowedInclude::relationship('posSession'),
            )
            ->defaultSort('-created_at')
            ->with(['customer', 'items'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     tax_id?: int|null,
     *     warehouse_id?: int|null,
     *     channel_id?: int|null,
     *     pos_session_id?: int|null,
     *     notes?: string|null,
     *     status?: string,
     *     items: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            /** @var Tenant $tenant */
            $tenant = tenant();
            $this->entitlements->assertCanCreateOrder($tenant);

            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($data['customer_id']);

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer is inactive.'],
                ]);
            }

            $channelId = $data['channel_id'] ?? null;
            if ($channelId !== null) {
                $channel = Channel::query()->findOrFail($channelId);
                if (! $channel->is_active) {
                    throw ValidationException::withMessages([
                        'channel_id' => ['The selected channel is inactive.'],
                    ]);
                }
            }

            $status = OrderStatus::tryFrom($data['status'] ?? OrderStatus::Draft->value) ?? OrderStatus::Draft;
            $lines = $this->buildLines($data['items'], $this->shouldEnforceStock($status), $customer, $channelId);
            $currency = $lines[0]['currency'];
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $tax = $this->resolveTax($data['tax_id'] ?? null);
            $taxAmount = $tax?->calculateTax($subtotal) ?? 0;
            $warehouseId = $this->resolveWarehouseId($data['warehouse_id'] ?? null);
            $orderTotal = $subtotal + $taxAmount;

            if ($status === OrderStatus::Confirmed) {
                $this->assertCreditLimit($customer, $orderTotal);
            }

            /** @var Order $order */
            $order = Order::query()->create([
                'number' => 'ORD-'.Str::upper(Str::random(10)),
                'customer_id' => $customer->id,
                'tax_id' => $tax?->id,
                'warehouse_id' => $warehouseId,
                'channel_id' => $channelId,
                'pos_session_id' => $data['pos_session_id'] ?? null,
                'status' => $status,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'total' => $orderTotal,
                'notes' => $data['notes'] ?? null,
                'placed_at' => $status === OrderStatus::Draft ? null : now(),
                'inventory_decremented' => false,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'product_sku' => $line['product_sku'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);
            }

            $this->syncInventoryAndInvoice($order);

            $order = $order->refresh()->load(['customer', 'items', 'salesInvoice', 'taxRate', 'warehouse', 'channel', 'posSession']);

            if ($status === OrderStatus::Pending && $order->warehouse_id !== null) {
                $this->reserveOrderStock($order);
            }

            if ($status === OrderStatus::Confirmed) {
                event(new OrderConfirmed($order, (string) $tenant->getTenantKey()));
            }

            return $order;
        });
    }

    public function find(Order $order): Order
    {
        return $order->loadMissing(['customer', 'items.product', 'salesInvoice', 'taxRate', 'warehouse']);
    }

    /**
     * @param  array{
     *     status?: string,
     *     notes?: string|null,
     *     tax_id?: int|null,
     *     warehouse_id?: int|null,
     *     items?: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $previousStatus = $order->status;
            /** @var Tenant $tenant */
            $tenant = tenant();

            if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Fulfilled) {
                throw ValidationException::withMessages([
                    'order' => ['Fulfilled or cancelled orders cannot be updated.'],
                ]);
            }

            if (isset($data['items']) && $order->inventory_decremented) {
                $this->restoreInventory($order);
            }

            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            if (array_key_exists('tax_id', $data)) {
                $order->tax_id = $this->resolveTax($data['tax_id'])?->id;
            }

            if (array_key_exists('warehouse_id', $data)) {
                if ($order->inventory_decremented) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => ['Warehouse cannot be changed after inventory has been decremented.'],
                    ]);
                }

                $order->warehouse_id = $this->resolveWarehouseId($data['warehouse_id']);
            }

            if (isset($data['status'])) {
                $status = OrderStatus::from($data['status']);
                $order->status = $status;

                if ($status !== OrderStatus::Draft && $order->placed_at === null) {
                    $order->placed_at = now();
                }
            }

            if (isset($data['items'])) {
                $customer = $order->customer ?? Customer::query()->findOrFail($order->customer_id);
                $lines = $this->buildLines($data['items'], $this->shouldEnforceStock($order->status), $customer, $order->channel_id);
                $currency = $lines[0]['currency'];
                $subtotal = array_sum(array_column($lines, 'line_total'));

                $order->items()->delete();

                foreach ($lines as $line) {
                    $order->items()->create([
                        'product_id' => $line['product_id'],
                        'product_name' => $line['product_name'],
                        'product_sku' => $line['product_sku'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                    ]);
                }

                $order->currency = $currency;
                $order->subtotal = $subtotal;
            }

            $tax = $order->tax_id !== null
                ? Tax::query()->find($order->tax_id)
                : null;
            $order->tax = $tax?->calculateTax((int) $order->subtotal) ?? 0;
            $order->total = (int) $order->subtotal + (int) $order->tax;

            if ($order->status === OrderStatus::Confirmed) {
                $customer = $order->customer ?? Customer::query()->findOrFail($order->customer_id);
                $this->assertCreditLimit($customer, (int) $order->total, $order->id);
            }

            $order->save();

            $this->syncInventoryAndInvoice($order);

            $order = $order->refresh()->load(['customer', 'items', 'salesInvoice', 'taxRate', 'warehouse', 'channel', 'posSession']);

            if ($order->status === OrderStatus::Pending && $order->warehouse_id !== null) {
                $this->reservations->releaseForOrder($order);
                $this->reserveOrderStock($order);
            }

            if (
                $order->status === OrderStatus::Confirmed
                && $previousStatus !== OrderStatus::Confirmed
            ) {
                event(new OrderConfirmed($order, (string) $tenant->getTenantKey()));
            }

            return $order;
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            if ($order->inventory_decremented) {
                $this->restoreInventory($order);
            } else {
                $this->reservations->releaseForOrder($order);
            }

            $order->delete();
        });
    }

    /**
     * @throws Throwable
     */
    private function syncInventoryAndInvoice(Order $order): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            $this->restoreInventory($order);
            $this->reservations->releaseForOrder($order);

            return;
        }

        if ($this->shouldDecrementInventory($order->status)) {
            $this->applyInventory($order);
            $this->salesInvoices->ensureForOrder($order->load('items'));
        }
    }

    private function shouldEnforceStock(OrderStatus $status): bool
    {
        return $status !== OrderStatus::Draft && $status !== OrderStatus::Cancelled;
    }

    private function shouldDecrementInventory(OrderStatus $status): bool
    {
        return $status === OrderStatus::Confirmed || $status === OrderStatus::Fulfilled;
    }

    private function applyInventory(Order $order): void
    {
        if ($order->inventory_decremented) {
            return;
        }

        $order->loadMissing('items');

        if ($order->warehouse_id !== null) {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->findOrFail($order->warehouse_id);

            foreach ($order->items as $item) {
                /** @var Product $product */
                $product = Product::query()->findOrFail($item->product_id);

                if (! $product->track_inventory) {
                    continue;
                }

                $onHand = $this->ledger->onHand($warehouse, $product);

                if ($onHand < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient warehouse stock for product #{$item->product_id}. Available: {$onHand}."],
                    ]);
                }

                $this->ledger->move(
                    warehouse: $warehouse,
                    product: $product,
                    quantityDelta: -1 * $item->quantity,
                    reason: StockMovementReason::Sale,
                    reference: $order,
                    notes: "Sale for order {$order->number}",
                );

                $this->valuation->consume($product, $item->quantity);
            }

            $this->reservations->consumeForOrder($order);
        } else {
            foreach ($order->items as $item) {
                /** @var Product|null $product */
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product === null || ! $product->track_inventory || $product->stock_quantity === null) {
                    continue;
                }

                if ($product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for {$product->sku}. Available: {$product->stock_quantity}."],
                    ]);
                }

                $product->decrement('stock_quantity', $item->quantity);
            }
        }

        $order->forceFill(['inventory_decremented' => true])->save();
    }

    private function restoreInventory(Order $order): void
    {
        if (! $order->inventory_decremented) {
            return;
        }

        $order->loadMissing('items');

        if ($order->warehouse_id !== null) {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->findOrFail($order->warehouse_id);

            foreach ($order->items as $item) {
                /** @var Product $product */
                $product = Product::query()->findOrFail($item->product_id);

                if (! $product->track_inventory) {
                    continue;
                }

                $this->ledger->move(
                    warehouse: $warehouse,
                    product: $product,
                    quantityDelta: $item->quantity,
                    reason: StockMovementReason::SaleReversal,
                    reference: $order,
                    notes: "Reversal for order {$order->number}",
                );
            }
        } else {
            foreach ($order->items as $item) {
                /** @var Product|null $product */
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if ($product === null || ! $product->track_inventory || $product->stock_quantity === null) {
                    continue;
                }

                $product->increment('stock_quantity', $item->quantity);
            }
        }

        $order->forceFill(['inventory_decremented' => false])->save();
    }

    private function reserveOrderStock(Order $order): void
    {
        if ($order->warehouse_id === null) {
            return;
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($order->warehouse_id);
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($item->product_id);

            if (! $product->track_inventory) {
                continue;
            }

            $this->reservations->reserve(
                warehouse: $warehouse,
                product: $product,
                quantity: $item->quantity,
                order: $order,
                expiresAt: now()->addDays(7),
            );
        }
    }

    private function resolveTax(?int $taxId): ?Tax
    {
        if ($taxId !== null) {
            /** @var Tax $tax */
            $tax = Tax::query()->whereKey($taxId)->where('is_active', true)->firstOrFail();

            return $tax;
        }

        return Tax::query()->where('is_default', true)->where('is_active', true)->first();
    }

    private function resolveWarehouseId(?int $warehouseId): ?int
    {
        if ($warehouseId !== null) {
            Warehouse::query()->whereKey($warehouseId)->where('is_active', true)->firstOrFail();

            return $warehouseId;
        }

        return Warehouse::query()->where('is_default', true)->where('is_active', true)->value('id');
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array{product_id: int, product_name: string, product_sku: string, quantity: int, unit_price: int, line_total: int, currency: string}>
     */
    private function buildLines(array $items, bool $enforceStock = false, ?Customer $customer = null, ?int $channelId = null): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['An order must include at least one item.'],
            ]);
        }

        $lines = [];
        $currency = null;
        /** @var array<int, int> $requestedByProduct */
        $requestedByProduct = [];
        $customer?->loadMissing('group');

        foreach ($items as $index => $item) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($item['product_id']);

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['The selected product is inactive.'],
                ]);
            }

            $currency ??= $product->currency;

            if ($product->currency !== $currency) {
                throw ValidationException::withMessages([
                    'items' => ['All order items must share the same currency.'],
                ]);
            }

            $quantity = $item['quantity'];
            $quote = $this->pricing->quote($product, $quantity, $customer, null, $channelId);
            $unitPrice = $quote['unit_price'];

            if ($enforceStock && $product->track_inventory && $product->stock_quantity !== null) {
                $requestedByProduct[$product->id] = ($requestedByProduct[$product->id] ?? 0) + $quantity;

                if ($requestedByProduct[$product->id] > $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Insufficient stock for {$product->sku}. Available: {$product->stock_quantity}."],
                    ]);
                }
            }

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quote['line_total'],
                'currency' => $product->currency,
            ];
        }

        return $lines;
    }

    private function assertCreditLimit(Customer $customer, int $orderTotal, ?int $excludeOrderId = null): void
    {
        if ($customer->credit_limit === null) {
            return;
        }

        $openAr = $this->openAccountsReceivable($customer->id, $excludeOrderId);

        if ($openAr + $orderTotal > $customer->credit_limit) {
            throw ValidationException::withMessages([
                'customer_id' => ['Order would exceed the customer credit limit.'],
            ]);
        }
    }

    private function openAccountsReceivable(int $customerId, ?int $excludeOrderId = null): int
    {
        $query = SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->whereNotIn('status', [SalesInvoiceStatus::Paid, SalesInvoiceStatus::Void]);

        if ($excludeOrderId !== null) {
            $query->where('order_id', '!=', $excludeOrderId);
        }

        return $query->get()->sum(fn (SalesInvoice $invoice): int => $this->invoiceOutstandingBalance($invoice));
    }

    private function invoiceOutstandingBalance(SalesInvoice $invoice): int
    {
        $allocated = (int) SalesPaymentAllocation::query()
            ->where('sales_invoice_id', $invoice->id)
            ->whereHas('payment', fn ($query) => $query->where('status', SalesPaymentStatus::Completed))
            ->sum('amount');

        return max(0, $invoice->total - $allocated);
    }
}
