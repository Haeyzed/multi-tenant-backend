<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PurchaseAgreementStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Events\Tenant\Erp\PurchaseOrderApproved;
use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseAgreement;
use App\Models\Tenant\PurchaseAgreementItem;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierProduct;
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
 * Purchase order lifecycle and totals management.
 */
final class PurchaseOrderService
{
    /**
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(PurchaseOrder::class)
            ->with(['supplier', 'warehouse'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('purchase_agreement_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('total'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     supplier_id: int,
     *     purchase_agreement_id?: int|null,
     *     warehouse_id?: int|null,
     *     currency?: string|null,
     *     tax?: int,
     *     notes?: string|null,
     *     expected_at?: string|null,
     *     items: list<array{product_id: int, quantity: int, unit_cost?: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): PurchaseOrder
    {
        $this->assertSupplier($data['supplier_id']);
        $this->assertWarehouse($data['warehouse_id'] ?? null);
        $agreementId = $this->assertAgreement($data['purchase_agreement_id'] ?? null, $data['supplier_id']);

        return DB::transaction(function () use ($data, $agreementId): PurchaseOrder {
            $lines = $this->buildLines($data['items'], $data['supplier_id'], $agreementId);
            $currency = $data['currency'] ?? $lines[0]['currency'] ?? 'USD';
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $tax = $data['tax'] ?? 0;

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->create([
                'number' => 'PO-'.Str::upper(Str::random(10)),
                'supplier_id' => $data['supplier_id'],
                'purchase_agreement_id' => $agreementId,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'status' => PurchaseOrderStatus::Draft,
                'currency' => strtoupper($currency),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'notes' => $data['notes'] ?? null,
                'expected_at' => $data['expected_at'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $lines);

            return $this->find($purchaseOrder->refresh());
        });
    }

    public function find(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->loadMissing(['supplier', 'warehouse', 'items.product', 'approver']);
    }

    /**
     * @param  array{
     *     supplier_id?: int,
     *     warehouse_id?: int|null,
     *     currency?: string|null,
     *     tax?: int,
     *     notes?: string|null,
     *     expected_at?: string|null,
     *     items?: list<array{product_id: int, quantity: int, unit_cost?: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        $this->assertStatus($purchaseOrder, PurchaseOrderStatus::Draft);

        if (isset($data['supplier_id'])) {
            $this->assertSupplier($data['supplier_id']);
        }

        if (array_key_exists('warehouse_id', $data)) {
            $this->assertWarehouse($data['warehouse_id']);
        }

        return DB::transaction(function () use ($purchaseOrder, $data): PurchaseOrder {
            if (isset($data['supplier_id'])) {
                $purchaseOrder->supplier_id = $data['supplier_id'];
            }

            if (array_key_exists('warehouse_id', $data)) {
                $purchaseOrder->warehouse_id = $data['warehouse_id'];
            }

            if (array_key_exists('notes', $data)) {
                $purchaseOrder->notes = $data['notes'];
            }

            if (array_key_exists('expected_at', $data)) {
                $purchaseOrder->expected_at = $data['expected_at'];
            }

            if (isset($data['items'])) {
                $lines = $this->buildLines($data['items'], $purchaseOrder->supplier_id, $purchaseOrder->purchase_agreement_id);
                $purchaseOrder->currency = strtoupper($data['currency'] ?? $lines[0]['currency'] ?? $purchaseOrder->currency);
                $purchaseOrder->items()->delete();
                $this->syncItems($purchaseOrder, $lines);
            } elseif (isset($data['currency'])) {
                $purchaseOrder->currency = strtoupper($data['currency']);
            }

            if (array_key_exists('tax', $data)) {
                $purchaseOrder->tax = $data['tax'];
            }

            $this->rebuildTotals($purchaseOrder);
            $purchaseOrder->save();

            return $this->find($purchaseOrder->refresh());
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        $this->assertStatus($purchaseOrder, PurchaseOrderStatus::Draft);
        $purchaseOrder->delete();
    }

    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->assertStatus($purchaseOrder, PurchaseOrderStatus::Draft);
        $this->assertHasItems($purchaseOrder);

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Submitted,
            'ordered_at' => now(),
        ]);

        return $this->find($purchaseOrder->refresh());
    }

    public function approve(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->assertStatus($purchaseOrder, PurchaseOrderStatus::Submitted);

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        /** @var Tenant $tenant */
        $tenant = tenant();
        event(new PurchaseOrderApproved($purchaseOrder->refresh(), (string) $tenant->getTenantKey()));

        return $this->find($purchaseOrder->refresh());
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (in_array($purchaseOrder->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot cancel a purchase order in {$purchaseOrder->status->value} status."],
            ]);
        }

        if ($purchaseOrder->status === PurchaseOrderStatus::Approved) {
            $hasReceipts = $purchaseOrder->items()->where('quantity_received', '>', 0)->exists();

            if ($hasReceipts) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot cancel a purchase order that has received goods.'],
                ]);
            }
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        return $this->find($purchaseOrder->refresh());
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_cost?: int}>  $items
     * @return list<array{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: string,
     *     quantity: int,
     *     unit_cost: int,
     *     line_total: int,
     *     currency: string
     * }>
     */
    private function buildLines(array $items, int $supplierId, ?int $agreementId = null): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one purchase order item is required.'],
            ]);
        }

        $lines = [];

        foreach ($items as $index => $item) {
            /** @var Product|null $product */
            $product = Product::query()->find($item['product_id']);

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['The selected product is invalid.'],
                ]);
            }

            $quantity = (int) ($item['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            $agreementItem = null;

            if ($agreementId !== null) {
                $agreementItem = PurchaseAgreementItem::query()
                    ->where('purchase_agreement_id', $agreementId)
                    ->where('product_id', $product->id)
                    ->first();

                if ($agreementItem !== null && $quantity < $agreementItem->min_order_qty) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Quantity must be at least {$agreementItem->min_order_qty} per the purchase agreement."],
                    ]);
                }
            }

            $unitCost = $item['unit_cost']
                ?? ($agreementItem !== null ? (int) $agreementItem->unit_cost : null)
                ?? $this->resolveUnitCost($supplierId, $product->id, $product->unit_price);

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $quantity * $unitCost,
                'currency' => $product->currency,
            ];
        }

        return $lines;
    }

    private function resolveUnitCost(int $supplierId, int $productId, int $fallback): int
    {
        $supplierProduct = SupplierProduct::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->first();

        if ($supplierProduct !== null && $supplierProduct->unit_cost > 0) {
            return (int) $supplierProduct->unit_cost;
        }

        return $fallback;
    }

    private function assertAgreement(?int $agreementId, int $supplierId): ?int
    {
        if ($agreementId === null) {
            return null;
        }

        /** @var PurchaseAgreement|null $agreement */
        $agreement = PurchaseAgreement::query()->find($agreementId);

        if ($agreement === null) {
            throw ValidationException::withMessages([
                'purchase_agreement_id' => ['The selected purchase agreement is invalid.'],
            ]);
        }

        if ($agreement->supplier_id !== $supplierId) {
            throw ValidationException::withMessages([
                'purchase_agreement_id' => ['Purchase agreement supplier must match the purchase order supplier.'],
            ]);
        }

        if ($agreement->status !== PurchaseAgreementStatus::Active) {
            throw ValidationException::withMessages([
                'purchase_agreement_id' => ['Only active purchase agreements can be linked to a purchase order.'],
            ]);
        }

        return $agreement->id;
    }

    /**
     * @param  list<array{
     *     product_id: int,
     *     product_name: string,
     *     product_sku: string,
     *     quantity: int,
     *     unit_cost: int,
     *     line_total: int
     * }>  $lines
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $lines): void
    {
        foreach ($lines as $line) {
            $purchaseOrder->items()->create([
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'product_sku' => $line['product_sku'],
                'quantity' => $line['quantity'],
                'quantity_received' => 0,
                'unit_cost' => $line['unit_cost'],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    private function rebuildTotals(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('items');
        $purchaseOrder->subtotal = (int) $purchaseOrder->items->sum('line_total');
        $purchaseOrder->total = (int) $purchaseOrder->subtotal + (int) $purchaseOrder->tax;
    }

    private function assertSupplier(int $supplierId): void
    {
        if (! Supplier::query()->whereKey($supplierId)->exists()) {
            throw ValidationException::withMessages([
                'supplier_id' => ['The selected supplier is invalid.'],
            ]);
        }
    }

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

    private function assertHasItems(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Purchase order must have at least one item.'],
            ]);
        }
    }

    private function assertStatus(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $expected): void
    {
        if ($purchaseOrder->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Purchase order must be in {$expected->value} status."],
            ]);
        }
    }
}
