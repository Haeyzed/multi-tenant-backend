<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Product;
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
}
