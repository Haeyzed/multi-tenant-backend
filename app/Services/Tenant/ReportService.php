<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\GoodsReceiptStatus;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\SupplierInvoiceStatus;
use App\Enums\Tenant\SupplierPaymentStatus;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SupplierInvoice;
use App\Models\Tenant\SupplierPaymentAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenant operational reports.
 */
final class ReportService
{
    /**
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
}
