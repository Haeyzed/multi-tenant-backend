<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\SupplierInvoiceStatus;
use App\Events\Tenant\Erp\SupplierInvoiceIssued;
use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierInvoice;
use App\Models\Tenant\SupplierInvoiceItem;
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
 * Supplier invoice lifecycle and purchase order billing.
 */
final class SupplierInvoiceService
{
    /**
     * @return LengthAwarePaginator<int, SupplierInvoice>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierInvoice::class)
            ->with(['supplier'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('purchase_order_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('total'),
                AllowedSort::field('issued_at'),
                AllowedSort::field('due_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('supplier'),
                AllowedInclude::relationship('purchaseOrder'),
                AllowedInclude::relationship('goodsReceipt'),
                AllowedInclude::relationship('items'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function find(SupplierInvoice $invoice): SupplierInvoice
    {
        return $invoice->loadMissing(['supplier', 'purchaseOrder', 'goodsReceipt', 'items.product']);
    }

    /**
     * @param  array{
     *     supplier_id: int,
     *     currency: string,
     *     tax?: int,
     *     notes?: string|null,
     *     due_at?: string|null,
     *     purchase_order_id?: int|null,
     *     goods_receipt_id?: int|null,
     *     items: list<array{product_id?: int|null, description: string, quantity: int, unit_cost: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): SupplierInvoice
    {
        $this->assertSupplier($data['supplier_id']);

        return DB::transaction(function () use ($data): SupplierInvoice {
            $lines = $this->buildLines($data['items']);
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $tax = $data['tax'] ?? 0;

            /** @var SupplierInvoice $invoice */
            $invoice = SupplierInvoice::query()->create([
                'number' => 'SINV-'.Str::upper(Str::random(10)),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'status' => SupplierInvoiceStatus::Draft,
                'currency' => strtoupper($data['currency']),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $subtotal + $tax,
                'notes' => $data['notes'] ?? null,
                'due_at' => $data['due_at'] ?? null,
            ]);

            $this->syncItems($invoice, $lines);

            return $this->find($invoice->refresh());
        });
    }

    /**
     * @param  array{
     *     supplier_id?: int,
     *     currency?: string,
     *     tax?: int,
     *     notes?: string|null,
     *     due_at?: string|null,
     *     items?: list<array{product_id?: int|null, description: string, quantity: int, unit_cost: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(SupplierInvoice $invoice, array $data): SupplierInvoice
    {
        if ($invoice->status !== SupplierInvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Only draft supplier invoices can be updated.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $data): SupplierInvoice {
            if (isset($data['supplier_id'])) {
                $this->assertSupplier($data['supplier_id']);
                $invoice->supplier_id = $data['supplier_id'];
            }

            if (isset($data['currency'])) {
                $invoice->currency = strtoupper($data['currency']);
            }

            if (array_key_exists('notes', $data)) {
                $invoice->notes = $data['notes'];
            }

            if (array_key_exists('due_at', $data)) {
                $invoice->due_at = $data['due_at'];
            }

            if (isset($data['items'])) {
                $lines = $this->buildLines($data['items']);
                $invoice->subtotal = array_sum(array_column($lines, 'line_total'));
                $this->syncItems($invoice, $lines);
            }

            if (isset($data['tax'])) {
                $invoice->tax = $data['tax'];
            }

            $invoice->total = $invoice->subtotal + $invoice->tax;
            $invoice->save();

            return $this->find($invoice->refresh());
        });
    }

    public function delete(SupplierInvoice $invoice): void
    {
        if ($invoice->status !== SupplierInvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Only draft supplier invoices can be deleted. Void issued invoices instead.'],
            ]);
        }

        $invoice->delete();
    }

    /**
     * @throws Throwable
     */
    public function issue(SupplierInvoice $invoice): SupplierInvoice
    {
        if ($invoice->status !== SupplierInvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Only draft supplier invoices can be issued.'],
            ]);
        }

        return DB::transaction(function () use ($invoice): SupplierInvoice {
            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Issued,
                'issued_at' => now(),
            ])->save();

            $this->dispatchInvoiceIssued($invoice);

            return $this->find($invoice->refresh());
        });
    }

    /**
     * @throws Throwable
     */
    public function issueFromPurchaseOrder(PurchaseOrder $purchaseOrder): SupplierInvoice
    {
        $purchaseOrder->loadMissing('items');

        if (in_array($purchaseOrder->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Submitted, PurchaseOrderStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'purchase_order' => ['Supplier invoices can only be created from approved or received purchase orders.'],
            ]);
        }

        $existing = SupplierInvoice::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereNot('status', SupplierInvoiceStatus::Void)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'purchase_order' => ['An active supplier invoice already exists for this purchase order.'],
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder): SupplierInvoice {
            $lines = $purchaseOrder->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'description' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'line_total' => $item->line_total,
            ])->all();

            /** @var SupplierInvoice $invoice */
            $invoice = SupplierInvoice::query()->create([
                'number' => 'SINV-'.Str::upper(Str::random(10)),
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'status' => SupplierInvoiceStatus::Issued,
                'currency' => $purchaseOrder->currency,
                'subtotal' => $purchaseOrder->subtotal,
                'tax' => $purchaseOrder->tax,
                'total' => $purchaseOrder->total,
                'notes' => $purchaseOrder->notes,
                'issued_at' => now(),
                'due_at' => $purchaseOrder->expected_at,
            ]);

            $this->syncItems($invoice, $lines);

            $this->dispatchInvoiceIssued($invoice);

            return $this->find($invoice->refresh());
        });
    }

    /**
     * @throws Throwable
     */
    public function void(SupplierInvoice $invoice): SupplierInvoice
    {
        if ($invoice->status === SupplierInvoiceStatus::Paid) {
            throw ValidationException::withMessages([
                'invoice' => ['Paid supplier invoices cannot be voided.'],
            ]);
        }

        if ($invoice->status === SupplierInvoiceStatus::Void) {
            throw ValidationException::withMessages([
                'invoice' => ['Supplier invoice is already void.'],
            ]);
        }

        return DB::transaction(function () use ($invoice): SupplierInvoice {
            $invoice->forceFill([
                'status' => SupplierInvoiceStatus::Void,
                'paid_at' => null,
            ])->save();

            return $this->find($invoice->refresh());
        });
    }

    /**
     * @param  list<array{product_id?: int|null, description: string, quantity: int, unit_cost: int, line_total?: int}>  $lines
     */
    private function syncItems(SupplierInvoice $invoice, array $lines): void
    {
        $invoice->items()->delete();

        foreach ($lines as $line) {
            SupplierInvoiceItem::query()->create([
                'supplier_invoice_id' => $invoice->id,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => $line['line_total'] ?? ($line['quantity'] * $line['unit_cost']),
            ]);
        }
    }

    /**
     * @param  list<array{product_id?: int|null, description: string, quantity: int, unit_cost: int}>  $items
     * @return list<array{product_id: int|null, description: string, quantity: int, unit_cost: int, line_total: int}>
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $index => $item) {
            if ($item['quantity'] < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            if (isset($item['product_id'])) {
                Product::query()->findOrFail($item['product_id']);
            }

            $lines[] = [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'line_total' => $item['quantity'] * $item['unit_cost'],
            ];
        }

        return $lines;
    }

    private function assertSupplier(int $supplierId): void
    {
        Supplier::query()->findOrFail($supplierId);
    }

    private function dispatchInvoiceIssued(SupplierInvoice $invoice): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        event(new SupplierInvoiceIssued($invoice->loadMissing(['supplier', 'items']), (string) $tenant->getTenantKey()));
    }
}
