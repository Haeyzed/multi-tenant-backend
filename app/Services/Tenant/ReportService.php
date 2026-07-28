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
     * @return list<array{id: int, sku: string, name: string, stock_quantity: int}>
     */
    public function lowStock(int $threshold = 5): array
    {
        return Product::query()
            ->whereNotNull('stock_quantity')
            ->where('stock_quantity', '<=', $threshold)
            ->orderBy('stock_quantity')
            ->get(['id', 'sku', 'name', 'stock_quantity'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'stock_quantity' => (int) $product->stock_quantity,
            ])
            ->all();
    }
}
