<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\GoodsReceiptStatus;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\SalesInvoiceStatus;
use App\Enums\Tenant\SalesPaymentStatus;
use App\Enums\Tenant\SupplierInvoiceStatus;
use App\Enums\Tenant\SupplierPaymentStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\SalesInvoice;
use App\Models\Tenant\SalesPaymentAllocation;
use App\Models\Tenant\SupplierInvoice;
use App\Models\Tenant\SupplierPaymentAllocation;
use App\Models\Tenant\WarehouseStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenant operational reports.
 */
final class ReportService
{
    /**
     * Summarize order counts and revenue over a date range, grouped by status.
     *
     * @return array{
     *     from: string|null,
     *     to: string|null,
     *     orders_count: int,
     *     revenue_total: int,
     *     tax_total: int,
     *     by_status: array<string, array{count: int, total: int}>
     * }
     */
    public function salesSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Order::query()
            ->whereNotIn('status', [OrderStatus::Draft->value, OrderStatus::Cancelled->value]);

        if ($from !== null) {
            $query->where('placed_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('placed_at', '<=', $to);
        }

        $orders = $query->get(['status', 'total', 'tax']);

        $byStatus = [];

        foreach ($orders as $order) {
            $status = $order->status->value;
            $byStatus[$status] ??= ['count' => 0, 'total' => 0];
            $byStatus[$status]['count']++;
            $byStatus[$status]['total'] += $order->total;
        }

        return [
            'from' => $from?->toIso8601String(),
            'to' => $to?->toIso8601String(),
            'orders_count' => $orders->count(),
            'revenue_total' => (int) $orders->sum('total'),
            'tax_total' => (int) $orders->sum('tax'),
            'by_status' => $byStatus,
        ];
    }

    /**
     * List the best-selling products by quantity within a date range.
     *
     * @return list<array{product_id: int, product_sku: string, product_name: string, quantity_sold: int, revenue: int}>
     */
    public function topProducts(?Carbon $from = null, ?Carbon $to = null, int $limit = 10): array
    {
        $query = OrderItem::query()
            ->select([
                'order_items.product_id',
                'order_items.product_sku',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.line_total) as revenue'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', [OrderStatus::Draft->value, OrderStatus::Cancelled->value])
            ->groupBy('order_items.product_id', 'order_items.product_sku', 'order_items.product_name')
            ->orderByDesc('quantity_sold')
            ->limit($limit);

        if ($from !== null) {
            $query->where('orders.placed_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('orders.placed_at', '<=', $to);
        }

        return $query->get()
            ->map(fn ($row): array => [
                'product_id' => (int) $row->product_id,
                'product_sku' => (string) $row->product_sku,
                'product_name' => (string) $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * List on-hand inventory quantity and valuation per product, optionally scoped to a warehouse.
     *
     * @return list<array{product_id: int, sku: string, name: string, quantity: int, average_cost: int|null, valuation: int}>
     */
    public function inventoryValuation(?int $warehouseId = null): array
    {
        $query = DB::table('warehouse_stocks')
            ->join('products', 'products.id', '=', 'warehouse_stocks.product_id')
            ->whereNull('products.deleted_at')
            ->select([
                'products.id as product_id',
                'products.sku',
                'products.name',
                'products.average_cost',
                DB::raw('SUM(warehouse_stocks.quantity) as quantity'),
            ])
            ->groupBy('products.id', 'products.sku', 'products.name', 'products.average_cost')
            ->orderBy('products.sku');

        if ($warehouseId !== null) {
            $query->where('warehouse_stocks.warehouse_id', $warehouseId);
        }

        return $query->get()
            ->map(function ($row): array {
                $quantity = (int) $row->quantity;
                $averageCost = $row->average_cost !== null ? (int) $row->average_cost : null;

                return [
                    'product_id' => (int) $row->product_id,
                    'sku' => (string) $row->sku,
                    'name' => (string) $row->name,
                    'quantity' => $quantity,
                    'average_cost' => $averageCost,
                    'valuation' => $averageCost !== null ? $quantity * $averageCost : 0,
                ];
            })
            ->all();
    }

    /**
     * List products whose on-hand quantity is at or below their reorder point.
     *
     * @return list<array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     warehouse_id: int,
     *     on_hand: int,
     *     reorder_point: int,
     *     safety_stock: int|null,
     *     min_stock: int|null,
     *     max_stock: int|null
     * }>
     */
    public function lowStock(int $threshold = 5, ?int $warehouseId = null): array
    {
        $query = DB::table('warehouse_stocks')
            ->join('products', 'products.id', '=', 'warehouse_stocks.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.track_inventory', true)
            ->select([
                'products.id as product_id',
                'products.sku',
                'products.name',
                'warehouse_stocks.warehouse_id',
                'warehouse_stocks.quantity',
                'warehouse_stocks.reorder_point as stock_reorder_point',
                'warehouse_stocks.safety_stock as stock_safety_stock',
                'warehouse_stocks.min_stock as stock_min_stock',
                'warehouse_stocks.max_stock as stock_max_stock',
                'products.reorder_point as product_reorder_point',
                'products.safety_stock as product_safety_stock',
                'products.min_stock as product_min_stock',
                'products.max_stock as product_max_stock',
            ]);

        if ($warehouseId !== null) {
            $query->where('warehouse_stocks.warehouse_id', $warehouseId);
        }

        return $query->get()
            ->map(function ($row) use ($threshold): ?array {
                $onHand = (int) $row->quantity;
                $reorderPoint = $row->stock_reorder_point !== null
                    ? (int) $row->stock_reorder_point
                    : ($row->product_reorder_point !== null ? (int) $row->product_reorder_point : $threshold);

                if ($onHand > $reorderPoint) {
                    return null;
                }

                return [
                    'product_id' => (int) $row->product_id,
                    'sku' => (string) $row->sku,
                    'name' => (string) $row->name,
                    'warehouse_id' => (int) $row->warehouse_id,
                    'on_hand' => $onHand,
                    'reorder_point' => $reorderPoint,
                    'safety_stock' => $row->stock_safety_stock !== null
                        ? (int) $row->stock_safety_stock
                        : ($row->product_safety_stock !== null ? (int) $row->product_safety_stock : null),
                    'min_stock' => $row->stock_min_stock !== null
                        ? (int) $row->stock_min_stock
                        : ($row->product_min_stock !== null ? (int) $row->product_min_stock : null),
                    'max_stock' => $row->stock_max_stock !== null
                        ? (int) $row->stock_max_stock
                        : ($row->product_max_stock !== null ? (int) $row->product_max_stock : null),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Approximate gross profit using order line revenue minus product average cost × qty.
     *
     * @return array{from: string|null, to: string|null, revenue: int, cost: int, gross_profit: int, margin_bps: int}
     */
    public function grossProfit(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', [OrderStatus::Draft->value, OrderStatus::Cancelled->value])
            ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(order_items.quantity * COALESCE(products.average_cost, 0)), 0) as cost');

        if ($from !== null) {
            $query->where('orders.placed_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('orders.placed_at', '<=', $to);
        }

        $row = $query->first();
        $revenue = (int) ($row->revenue ?? 0);
        $cost = (int) ($row->cost ?? 0);
        $grossProfit = $revenue - $cost;

        return [
            'from' => $from?->toIso8601String(),
            'to' => $to?->toIso8601String(),
            'revenue' => $revenue,
            'cost' => $cost,
            'gross_profit' => $grossProfit,
            'margin_bps' => $revenue > 0 ? (int) round(($grossProfit / $revenue) * 10000) : 0,
        ];
    }

    /**
     * List stock lots ordered by receipt date to surface ageing/expiring inventory.
     *
     * @return list<array{
     *     product_id: int,
     *     sku: string,
     *     name: string,
     *     warehouse_id: int,
     *     warehouse_name: string,
     *     lot_id: int,
     *     lot_number: string,
     *     quantity: int,
     *     received_at: string|null,
     *     days_since_received: int|null,
     *     expires_at: string|null
     * }>
     */
    public function stockAgeing(?int $warehouseId = null): array
    {
        $query = DB::table('stock_lots')
            ->join('products', 'products.id', '=', 'stock_lots.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_lots.warehouse_id')
            ->whereNull('products.deleted_at')
            ->where('stock_lots.quantity', '>', 0)
            ->select([
                'products.id as product_id',
                'products.sku',
                'products.name',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'stock_lots.id as lot_id',
                'stock_lots.lot_number',
                'stock_lots.quantity',
                'stock_lots.received_at',
                'stock_lots.expires_at',
            ])
            ->orderBy('stock_lots.received_at');

        if ($warehouseId !== null) {
            $query->where('stock_lots.warehouse_id', $warehouseId);
        }

        return $query->get()
            ->map(function ($row): array {
                $receivedAt = $row->received_at !== null ? Carbon::parse((string) $row->received_at) : null;

                return [
                    'product_id' => (int) $row->product_id,
                    'sku' => (string) $row->sku,
                    'name' => (string) $row->name,
                    'warehouse_id' => (int) $row->warehouse_id,
                    'warehouse_name' => (string) $row->warehouse_name,
                    'lot_id' => (int) $row->lot_id,
                    'lot_number' => (string) $row->lot_number,
                    'quantity' => (int) $row->quantity,
                    'received_at' => $receivedAt?->toIso8601String(),
                    'days_since_received' => $receivedAt !== null ? (int) $receivedAt->diffInDays(now()) : null,
                    'expires_at' => $row->expires_at !== null ? Carbon::parse((string) $row->expires_at)->toIso8601String() : null,
                ];
            })
            ->all();
    }

    /**
     * Summarize purchase order counts and totals over a date range, grouped by status.
     *
     * @return array{
     *     from: string|null,
     *     to: string|null,
     *     purchase_orders_count: int,
     *     purchase_orders_total: int,
     *     goods_receipts_count: int,
     *     by_po_status: array<string, array{count: int, total: int}>
     * }
     */
    public function purchaseSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $poQuery = PurchaseOrder::query()
            ->whereNotIn('status', [PurchaseOrderStatus::Draft->value, PurchaseOrderStatus::Cancelled->value]);

        if ($from !== null) {
            $poQuery->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $poQuery->where('created_at', '<=', $to);
        }

        $purchaseOrders = $poQuery->get(['status', 'total']);

        $byStatus = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            $status = $purchaseOrder->status->value;
            $byStatus[$status] ??= ['count' => 0, 'total' => 0];
            $byStatus[$status]['count']++;
            $byStatus[$status]['total'] += $purchaseOrder->total;
        }

        $receiptQuery = GoodsReceipt::query()->where('status', GoodsReceiptStatus::Posted->value);

        if ($from !== null) {
            $receiptQuery->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $receiptQuery->where('created_at', '<=', $to);
        }

        return [
            'from' => $from?->toIso8601String(),
            'to' => $to?->toIso8601String(),
            'purchase_orders_count' => $purchaseOrders->count(),
            'purchase_orders_total' => (int) $purchaseOrders->sum('total'),
            'goods_receipts_count' => $receiptQuery->count(),
            'by_po_status' => $byStatus,
        ];
    }

    /**
     * Bucket outstanding supplier invoices by days overdue (accounts payable aging).
     *
     * @return array{
     *     as_of: string,
     *     total_outstanding: int,
     *     buckets: array<string, array{count: int, total_outstanding: int, invoices: list<array{
     *         id: int,
     *         number: string,
     *         supplier_id: int,
     *         supplier_name: string,
     *         currency: string,
     *         total: int,
     *         allocated: int,
     *         outstanding: int,
     *         due_at: string|null,
     *         days_overdue: int
     *     }>}>
     * }
     */
    public function apAging(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $asOfDate = $asOf->copy()->startOfDay();

        $invoices = SupplierInvoice::query()
            ->with('supplier')
            ->where('status', SupplierInvoiceStatus::Issued)
            ->get();

        $buckets = [
            'current' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '1_30' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '31_60' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '61_90' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '90_plus' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
        ];

        $totalOutstanding = 0;

        foreach ($invoices as $invoice) {
            $allocated = (int) SupplierPaymentAllocation::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->whereHas('payment', fn ($query) => $query->where('status', SupplierPaymentStatus::Completed))
                ->sum('amount');

            $outstanding = $invoice->total - $allocated;

            if ($outstanding <= 0) {
                continue;
            }

            $dueAt = $invoice->due_at ?? $invoice->issued_at ?? $invoice->created_at;
            $daysOverdue = 0;

            if ($dueAt !== null) {
                $dueDay = $dueAt->copy()->startOfDay();

                if ($dueDay->lt($asOfDate)) {
                    $daysOverdue = (int) $dueDay->diffInDays($asOfDate);
                }
            }

            $bucket = match (true) {
                $daysOverdue === 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => '90_plus',
            };

            $entry = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'supplier_id' => $invoice->supplier_id,
                'supplier_name' => $invoice->supplier->name,
                'currency' => $invoice->currency,
                'total' => $invoice->total,
                'allocated' => $allocated,
                'outstanding' => $outstanding,
                'due_at' => $invoice->due_at?->toIso8601String(),
                'days_overdue' => $daysOverdue,
            ];

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['total_outstanding'] += $outstanding;
            $buckets[$bucket]['invoices'][] = $entry;
            $totalOutstanding += $outstanding;
        }

        return [
            'as_of' => $asOf->toIso8601String(),
            'total_outstanding' => $totalOutstanding,
            'buckets' => $buckets,
        ];
    }

    /**
     * Bucket outstanding sales invoices by days overdue (accounts receivable aging).
     *
     * @return array{
     *     as_of: string,
     *     total_outstanding: int,
     *     buckets: array<string, array{count: int, total_outstanding: int, invoices: list<array<string, mixed>>}>
     * }
     */
    public function arAging(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $asOfDate = $asOf->copy()->startOfDay();

        $invoices = SalesInvoice::query()
            ->with('customer')
            ->where('status', SalesInvoiceStatus::Issued)
            ->get();

        $buckets = [
            'current' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '1_30' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '31_60' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '61_90' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
            '90_plus' => ['count' => 0, 'total_outstanding' => 0, 'invoices' => []],
        ];

        $totalOutstanding = 0;

        foreach ($invoices as $invoice) {
            $allocated = (int) SalesPaymentAllocation::query()
                ->where('sales_invoice_id', $invoice->id)
                ->whereHas('payment', fn ($query) => $query->where('status', SalesPaymentStatus::Completed))
                ->sum('amount');

            $outstanding = $invoice->total - $allocated;

            if ($outstanding <= 0) {
                continue;
            }

            $dueAt = $invoice->issued_at ?? $invoice->created_at;
            $daysOverdue = 0;

            if ($dueAt !== null) {
                $dueDay = $dueAt->copy()->startOfDay();

                if ($dueDay->lt($asOfDate)) {
                    $daysOverdue = (int) $dueDay->diffInDays($asOfDate);
                }
            }

            $bucket = match (true) {
                $daysOverdue === 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => '90_plus',
            };

            $entry = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer->name,
                'currency' => $invoice->currency,
                'total' => $invoice->total,
                'allocated' => $allocated,
                'outstanding' => $outstanding,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'days_overdue' => $daysOverdue,
            ];

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['total_outstanding'] += $outstanding;
            $buckets[$bucket]['invoices'][] = $entry;
            $totalOutstanding += $outstanding;
        }

        return [
            'as_of' => $asOf->toIso8601String(),
            'total_outstanding' => $totalOutstanding,
            'buckets' => $buckets,
        ];
    }

    /**
     * Summarize per-customer order counts, revenue, and open accounts receivable.
     *
     * @return array{
     *     from: string|null,
     *     to: string|null,
     *     customers: list<array{
     *         customer_id: int,
     *         customer_name: string,
     *         orders_count: int,
     *         revenue: int,
     *         open_ar: int
     *     }>
     * }
     */
    public function customerSummary(?Carbon $from = null, ?Carbon $to = null, int $limit = 50): array
    {
        $ordersQuery = Order::query()
            ->whereNotIn('status', [OrderStatus::Draft->value, OrderStatus::Cancelled->value]);

        if ($from !== null) {
            $ordersQuery->where('placed_at', '>=', $from);
        }

        if ($to !== null) {
            $ordersQuery->where('placed_at', '<=', $to);
        }

        $orderRows = $ordersQuery
            ->select([
                'customer_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(total) as revenue'),
            ])
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit(max(1, min(100, $limit)))
            ->get();

        $customerIds = $orderRows->pluck('customer_id')->all();
        $customers = Customer::query()->whereIn('id', $customerIds)->get()->keyBy('id');

        $openArByCustomer = [];

        if ($customerIds !== []) {
            $invoices = SalesInvoice::query()
                ->whereIn('customer_id', $customerIds)
                ->where('status', SalesInvoiceStatus::Issued)
                ->get();

            foreach ($invoices as $invoice) {
                $allocated = (int) SalesPaymentAllocation::query()
                    ->where('sales_invoice_id', $invoice->id)
                    ->whereHas('payment', fn ($query) => $query->where('status', SalesPaymentStatus::Completed))
                    ->sum('amount');

                $outstanding = $invoice->total - $allocated;

                if ($outstanding <= 0) {
                    continue;
                }

                $openArByCustomer[$invoice->customer_id] = ($openArByCustomer[$invoice->customer_id] ?? 0) + $outstanding;
            }
        }

        return [
            'from' => $from?->toIso8601String(),
            'to' => $to?->toIso8601String(),
            'customers' => $orderRows->map(function ($row) use ($customers, $openArByCustomer): array {
                $customerId = (int) $row->customer_id;

                return [
                    'customer_id' => $customerId,
                    'customer_name' => (string) ($customers->get($customerId)?->name ?? ''),
                    'orders_count' => (int) $row->orders_count,
                    'revenue' => (int) $row->revenue,
                    'open_ar' => (int) ($openArByCustomer[$customerId] ?? 0),
                ];
            })->all(),
        ];
    }

    /**
     * Summarize SKU count, stock quantities, and valuation for a warehouse (or all warehouses).
     *
     * @return array{
     *     warehouse_id: int|null,
     *     sku_count: int,
     *     on_hand: int,
     *     damaged: int,
     *     on_hold: int,
     *     valuation: int
     * }
     */
    public function warehouseSummary(?int $warehouseId = null): array
    {
        $query = WarehouseStock::query()
            ->join('products', 'products.id', '=', 'warehouse_stocks.product_id')
            ->whereNull('products.deleted_at');

        if ($warehouseId !== null) {
            $query->where('warehouse_stocks.warehouse_id', $warehouseId);
        }

        $row = $query
            ->selectRaw('COUNT(DISTINCT warehouse_stocks.product_id) as sku_count')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.quantity), 0) as on_hand')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.damaged_quantity), 0) as damaged')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.on_hold_quantity), 0) as on_hold')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.quantity * COALESCE(products.average_cost, 0)), 0) as valuation')
            ->first();

        return [
            'warehouse_id' => $warehouseId,
            'sku_count' => (int) ($row->sku_count ?? 0),
            'on_hand' => (int) ($row->on_hand ?? 0),
            'damaged' => (int) ($row->damaged ?? 0),
            'on_hold' => (int) ($row->on_hold ?? 0),
            'valuation' => (int) ($row->valuation ?? 0),
        ];
    }

    /**
     * List open purchase order lines expected to arrive, grouped by product and warehouse.
     *
     * @return array{
     *     as_of: string|null,
     *     warehouse_id: int|null,
     *     lines: list<array{
     *         product_id: int,
     *         product_sku: string,
     *         product_name: string,
     *         warehouse_id: int|null,
     *         quantity_on_order: int,
     *         expected_at: string|null,
     *         purchase_order_ids: list<int>
     *     }>
     * }
     */
    public function incomingStock(?int $warehouseId = null, ?Carbon $asOf = null): array
    {
        $openStatuses = [
            PurchaseOrderStatus::Submitted->value,
            PurchaseOrderStatus::Approved->value,
            PurchaseOrderStatus::PartiallyReceived->value,
        ];

        $query = PurchaseOrderItem::query()
            ->select([
                'purchase_order_items.product_id',
                'products.sku as product_sku',
                'products.name as product_name',
                'purchase_orders.warehouse_id',
                'purchase_orders.expected_at',
                'purchase_orders.id as purchase_order_id',
                DB::raw('(purchase_order_items.quantity - purchase_order_items.quantity_received) as open_qty'),
            ])
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->whereNull('products.deleted_at')
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.status', $openStatuses)
            ->whereRaw('purchase_order_items.quantity > purchase_order_items.quantity_received');

        if ($warehouseId !== null) {
            $query->where('purchase_orders.warehouse_id', $warehouseId);
        }

        if ($asOf !== null) {
            $query->where(function ($inner) use ($asOf): void {
                $inner->whereNull('purchase_orders.expected_at')
                    ->orWhere('purchase_orders.expected_at', '<=', $asOf);
            });
        }

        $grouped = [];

        foreach ($query->get() as $row) {
            $key = ((int) $row->product_id).':'.((string) ($row->warehouse_id ?? 'null'));

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_id' => (int) $row->product_id,
                    'product_sku' => (string) $row->product_sku,
                    'product_name' => (string) $row->product_name,
                    'warehouse_id' => $row->warehouse_id !== null ? (int) $row->warehouse_id : null,
                    'quantity_on_order' => 0,
                    'expected_at' => $row->expected_at !== null ? Carbon::parse($row->expected_at)->toIso8601String() : null,
                    'purchase_order_ids' => [],
                ];
            }

            $grouped[$key]['quantity_on_order'] += max(0, (int) $row->open_qty);
            $grouped[$key]['purchase_order_ids'][] = (int) $row->purchase_order_id;

            if ($row->expected_at !== null) {
                $expected = Carbon::parse($row->expected_at)->toIso8601String();

                if ($grouped[$key]['expected_at'] === null || $expected < $grouped[$key]['expected_at']) {
                    $grouped[$key]['expected_at'] = $expected;
                }
            }
        }

        return [
            'as_of' => $asOf?->toIso8601String(),
            'warehouse_id' => $warehouseId,
            'lines' => array_values($grouped),
        ];
    }

    /**
     * Project future demand and suggest reorder quantities from recent sales velocity.
     *
     * @return array{
     *     from: string|null,
     *     to: string|null,
     *     horizon_days: int,
     *     products: list<array{
     *         product_id: int,
     *         sku: string,
     *         name: string,
     *         quantity_sold: int,
     *         daily_velocity: float,
     *         projected_demand: int,
     *         on_hand: int,
     *         qty_on_order: int,
     *         reorder_point: int,
     *         suggested_reorder_qty: int
     *     }>
     * }
     */
    public function demandForecast(?Carbon $from = null, ?Carbon $to = null, int $horizonDays = 30): array
    {
        $to ??= now();
        $from ??= $to->copy()->subDays(30);
        $horizonDays = max(1, min(365, $horizonDays));
        $windowDays = max(1, (int) $from->diffInDays($to));

        $soldRows = OrderItem::query()
            ->select([
                'order_items.product_id',
                'products.sku',
                'products.name',
                'products.reorder_point',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNull('products.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', [
                OrderStatus::Confirmed->value,
                OrderStatus::PartiallyFulfilled->value,
                OrderStatus::Fulfilled->value,
            ])
            ->where('orders.placed_at', '>=', $from)
            ->where('orders.placed_at', '<=', $to)
            ->groupBy('order_items.product_id', 'products.sku', 'products.name', 'products.reorder_point')
            ->orderByDesc('quantity_sold')
            ->limit(100)
            ->get();

        $productIds = $soldRows->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
        $onHandByProduct = [];
        $onOrderByProduct = [];

        if ($productIds !== []) {
            $onHandByProduct = WarehouseStock::query()
                ->whereIn('product_id', $productIds)
                ->selectRaw('product_id, SUM(quantity) as on_hand')
                ->groupBy('product_id')
                ->pluck('on_hand', 'product_id')
                ->map(fn ($qty): int => (int) $qty)
                ->all();

            $incoming = $this->incomingStock();
            foreach ($incoming['lines'] as $line) {
                $productId = $line['product_id'];
                $onOrderByProduct[$productId] = ($onOrderByProduct[$productId] ?? 0) + $line['quantity_on_order'];
            }
        }

        $products = $soldRows->map(function ($row) use ($windowDays, $horizonDays, $onHandByProduct, $onOrderByProduct): array {
            $productId = (int) $row->product_id;
            $quantitySold = (int) $row->quantity_sold;
            $dailyVelocity = round($quantitySold / $windowDays, 4);
            $projectedDemand = (int) ceil($dailyVelocity * $horizonDays);
            $onHand = (int) ($onHandByProduct[$productId] ?? 0);
            $qtyOnOrder = (int) ($onOrderByProduct[$productId] ?? 0);
            $reorderPoint = (int) ($row->reorder_point ?? 0);
            $suggested = max(0, $projectedDemand + $reorderPoint - $onHand - $qtyOnOrder);

            return [
                'product_id' => $productId,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'quantity_sold' => $quantitySold,
                'daily_velocity' => $dailyVelocity,
                'projected_demand' => $projectedDemand,
                'on_hand' => $onHand,
                'qty_on_order' => $qtyOnOrder,
                'reorder_point' => $reorderPoint,
                'suggested_reorder_qty' => $suggested,
            ];
        })->all();

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'horizon_days' => $horizonDays,
            'products' => $products,
        ];
    }
}
