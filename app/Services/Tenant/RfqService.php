<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\SupplierQuoteStatus;
use App\Enums\Tenant\SupplierRfqStatus;
use App\Events\Tenant\Erp\RfqQuoteAccepted;
use App\Events\Tenant\Erp\RfqSent;
use App\Models\Central\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseRequest;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierQuote;
use App\Models\Tenant\SupplierRfq;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Supplier request-for-quotation lifecycle and conversion to purchase orders.
 */
final class RfqService
{
    public function __construct(private PurchaseOrderService $purchaseOrders) {}

    /**
     * @return LengthAwarePaginator<int, SupplierRfq>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierRfq::class)
            ->with(['purchaseRequest', 'creator', 'items.product'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('purchase_request_id'),
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
     *     purchase_request_id?: int|null,
     *     notes?: string|null,
     *     closes_at?: string|null,
     *     items: list<array{product_id: int, quantity: int, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): SupplierRfq
    {
        return DB::transaction(function () use ($data): SupplierRfq {
            $items = $data['items'] ?? [];

            if (isset($data['purchase_request_id']) && $data['purchase_request_id'] !== null) {
                /** @var PurchaseRequest|null $purchaseRequest */
                $purchaseRequest = PurchaseRequest::query()->with('items')->find($data['purchase_request_id']);

                if ($purchaseRequest === null) {
                    throw ValidationException::withMessages([
                        'purchase_request_id' => ['The selected purchase request is invalid.'],
                    ]);
                }

                if ($items === []) {
                    $items = $purchaseRequest->items
                        ->map(fn ($item): array => [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'notes' => $item->notes,
                        ])
                        ->all();
                }
            }

            /** @var SupplierRfq $rfq */
            $rfq = SupplierRfq::query()->create([
                'number' => 'RFQ-'.Str::upper(Str::random(10)),
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'status' => SupplierRfqStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'closes_at' => $data['closes_at'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncItems($rfq, $items);

            return $this->find($rfq->refresh());
        });
    }

    /**
     * Load the RFQ with its related purchase request, creator, items, and supplier quotes.
     */
    public function find(SupplierRfq $rfq): SupplierRfq
    {
        return $rfq->loadMissing([
            'purchaseRequest',
            'creator',
            'items.product',
            'quotes.supplier',
            'quotes.items.product',
        ]);
    }

    /**
     * @param  array{
     *     notes?: string|null,
     *     closes_at?: string|null,
     *     items?: list<array{product_id: int, quantity: int, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(SupplierRfq $rfq, array $data): SupplierRfq
    {
        $this->assertRfqStatus($rfq, SupplierRfqStatus::Draft);

        return DB::transaction(function () use ($rfq, $data): SupplierRfq {
            if (array_key_exists('notes', $data)) {
                $rfq->notes = $data['notes'];
            }

            if (array_key_exists('closes_at', $data)) {
                $rfq->closes_at = $data['closes_at'];
            }

            if (isset($data['items'])) {
                $rfq->items()->delete();
                $this->syncItems($rfq, $data['items']);
            }

            $rfq->save();

            return $this->find($rfq->refresh());
        });
    }

    /**
     * Delete a draft RFQ.
     *
     * @throws ValidationException if the RFQ is not in draft status
     */
    public function delete(SupplierRfq $rfq): void
    {
        $this->assertRfqStatus($rfq, SupplierRfqStatus::Draft);
        $rfq->delete();
    }

    /**
     * @param  list<int>  $supplierIds
     *
     * @throws Throwable
     */
    public function send(SupplierRfq $rfq, array $supplierIds): SupplierRfq
    {
        $this->assertRfqStatus($rfq, SupplierRfqStatus::Draft);
        $this->assertHasItems($rfq);

        if ($supplierIds === []) {
            throw ValidationException::withMessages([
                'supplier_ids' => ['At least one supplier is required.'],
            ]);
        }

        return DB::transaction(function () use ($rfq, $supplierIds): SupplierRfq {
            $rfq->loadMissing('items.product');
            $currency = strtoupper($rfq->items->first()?->product?->currency ?? 'USD');

            foreach ($supplierIds as $index => $supplierId) {
                if (! Supplier::query()->whereKey($supplierId)->exists()) {
                    throw ValidationException::withMessages([
                        "supplier_ids.{$index}" => ['The selected supplier is invalid.'],
                    ]);
                }

                SupplierQuote::query()->firstOrCreate(
                    [
                        'supplier_rfq_id' => $rfq->id,
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'status' => SupplierQuoteStatus::Pending,
                        'currency' => $currency,
                    ],
                );
            }

            $rfq->update([
                'status' => SupplierRfqStatus::Sent,
                'sent_at' => now(),
            ]);

            /** @var Tenant $tenant */
            $tenant = tenant();
            event(new RfqSent($rfq->refresh(), (string) $tenant->getTenantKey()));

            return $this->find($rfq->refresh());
        });
    }

    /**
     * Cancel an RFQ that has not yet been closed.
     *
     * @throws ValidationException if the RFQ is not draft or sent
     */
    public function cancel(SupplierRfq $rfq): SupplierRfq
    {
        if (! in_array($rfq->status, [SupplierRfqStatus::Draft, SupplierRfqStatus::Sent], true)) {
            throw ValidationException::withMessages([
                'status' => ['RFQ can only be cancelled from draft or sent status.'],
            ]);
        }

        $rfq->update(['status' => SupplierRfqStatus::Cancelled]);

        return $this->find($rfq->refresh());
    }

    /**
     * @return LengthAwarePaginator<int, SupplierQuote>
     */
    public function listQuotes(SupplierRfq $rfq, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierQuote::query()->where('supplier_rfq_id', $rfq->id))
            ->with(['supplier', 'items.product'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('supplier_id'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('status'),
                AllowedSort::field('submitted_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Load a supplier quote with its related RFQ, supplier, and items.
     */
    public function findQuote(SupplierQuote $quote): SupplierQuote
    {
        return $quote->loadMissing(['rfq.items.product', 'supplier', 'items.product']);
    }

    /**
     * @param  array{
     *     currency?: string,
     *     notes?: string|null,
     *     valid_until?: string|null,
     *     items: list<array{product_id: int, quantity: int, unit_cost: int, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function submitQuote(SupplierQuote $quote, array $data): SupplierQuote
    {
        $quote->loadMissing('rfq');
        $this->assertRfqStatus($quote->rfq, SupplierRfqStatus::Sent);

        if (! in_array($quote->status, [SupplierQuoteStatus::Pending, SupplierQuoteStatus::Submitted], true)) {
            throw ValidationException::withMessages([
                'status' => ['Quote must be pending or submitted to update pricing.'],
            ]);
        }

        return DB::transaction(function () use ($quote, $data): SupplierQuote {
            $quote->items()->delete();
            $this->syncQuoteItems($quote, $data['items']);

            $quote->update([
                'status' => SupplierQuoteStatus::Submitted,
                'currency' => strtoupper($data['currency'] ?? $quote->currency),
                'notes' => $data['notes'] ?? $quote->notes,
                'valid_until' => $data['valid_until'] ?? $quote->valid_until,
                'submitted_at' => now(),
            ]);

            return $this->findQuote($quote->refresh());
        });
    }

    /**
     * @param  array{warehouse_id?: int|null, tax?: int, notes?: string|null, expected_at?: string|null}  $convertData
     *
     * @throws Throwable
     */
    public function acceptQuote(SupplierQuote $quote, array $convertData = []): PurchaseOrder
    {
        $quote->loadMissing(['rfq.purchaseRequest', 'items']);
        $this->assertRfqStatus($quote->rfq, SupplierRfqStatus::Sent);
        $this->assertQuoteStatus($quote, SupplierQuoteStatus::Submitted);

        if ($quote->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Accepted quote must have at least one item.'],
            ]);
        }

        return DB::transaction(function () use ($quote, $convertData): PurchaseOrder {
            $rfq = $quote->rfq;

            $warehouseId = $convertData['warehouse_id']
                ?? $rfq->purchaseRequest?->warehouse_id;

            $items = $quote->items
                ->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                ])
                ->all();

            $purchaseOrder = $this->purchaseOrders->create([
                'supplier_id' => $quote->supplier_id,
                'warehouse_id' => $warehouseId,
                'currency' => $quote->currency,
                'tax' => $convertData['tax'] ?? 0,
                'notes' => $convertData['notes'] ?? $quote->notes ?? $rfq->notes,
                'expected_at' => $convertData['expected_at'] ?? null,
                'items' => $items,
            ]);

            $quote->update(['status' => SupplierQuoteStatus::Accepted]);

            SupplierQuote::query()
                ->where('supplier_rfq_id', $rfq->id)
                ->whereKeyNot($quote->id)
                ->whereIn('status', [SupplierQuoteStatus::Pending->value, SupplierQuoteStatus::Submitted->value])
                ->update(['status' => SupplierQuoteStatus::Rejected->value]);

            $rfq->update(['status' => SupplierRfqStatus::Closed]);

            /** @var Tenant $tenant */
            $tenant = tenant();
            event(new RfqQuoteAccepted($quote->refresh(), $purchaseOrder, (string) $tenant->getTenantKey()));

            return $this->purchaseOrders->find($purchaseOrder);
        });
    }

    /**
     * Reject a pending or submitted supplier quote.
     *
     * @throws ValidationException if the RFQ is not sent or the quote is not pending/submitted
     */
    public function rejectQuote(SupplierQuote $quote): SupplierQuote
    {
        $quote->loadMissing('rfq');
        $this->assertRfqStatus($quote->rfq, SupplierRfqStatus::Sent);

        if (! in_array($quote->status, [SupplierQuoteStatus::Pending, SupplierQuoteStatus::Submitted], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only pending or submitted quotes can be rejected.'],
            ]);
        }

        $quote->update(['status' => SupplierQuoteStatus::Rejected]);

        return $this->findQuote($quote->refresh());
    }

    /**
     * @param  list<array{product_id: int, quantity: int, notes?: string|null}>  $items
     */
    private function syncItems(SupplierRfq $rfq, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one RFQ item is required.'],
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

            $rfq->items()->create([
                'product_id' => $product->id,
                'quantity' => (int) $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_cost: int, notes?: string|null}>  $items
     */
    private function syncQuoteItems(SupplierQuote $quote, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one quote item is required.'],
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

            $quantity = (int) ($item['quantity'] ?? 0);
            $unitCost = (int) ($item['unit_cost'] ?? -1);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            if ($unitCost < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_cost" => ['Unit cost must be zero or greater.'],
                ]);
            }

            $quote->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $quantity * $unitCost,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /**
     * Ensure the RFQ has at least one item.
     *
     * @throws ValidationException if the RFQ has no items
     */
    private function assertHasItems(SupplierRfq $rfq): void
    {
        if ($rfq->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['RFQ must have at least one item.'],
            ]);
        }
    }

    /**
     * Ensure the RFQ is in the expected status.
     *
     * @throws ValidationException if the RFQ status does not match
     */
    private function assertRfqStatus(SupplierRfq $rfq, SupplierRfqStatus $expected): void
    {
        if ($rfq->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["RFQ must be in {$expected->value} status."],
            ]);
        }
    }

    /**
     * Ensure the supplier quote is in the expected status.
     *
     * @throws ValidationException if the quote status does not match
     */
    private function assertQuoteStatus(SupplierQuote $quote, SupplierQuoteStatus $expected): void
    {
        if ($quote->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Quote must be in {$expected->value} status."],
            ]);
        }
    }
}
