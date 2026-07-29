<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\QuotationStatus;
use App\Events\Tenant\Erp\QuotationAccepted;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant sales quotation lifecycle and conversion to orders.
 */
final class QuotationService
{
    public function __construct(
        private OrderService $orders,
        private PricingEngine $pricing,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Quotation>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Quotation::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('total'),
                AllowedSort::field('valid_until'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('customer'),
                AllowedInclude::relationship('items'),
                AllowedInclude::relationship('taxRate'),
                AllowedInclude::relationship('convertedOrder'),
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
     *     notes?: string|null,
     *     valid_until?: string|null,
     *     items: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data): Quotation {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($data['customer_id']);

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer is inactive.'],
                ]);
            }

            $lines = $this->buildLines($data['items'], $customer);
            $currency = $lines[0]['currency'];
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $tax = $this->resolveTax($data['tax_id'] ?? null);
            $taxAmount = $tax?->calculateTax($subtotal) ?? 0;

            /** @var Quotation $quotation */
            $quotation = Quotation::query()->create([
                'number' => 'QUO-'.Str::upper(Str::random(10)),
                'customer_id' => $customer->id,
                'tax_id' => $tax?->id,
                'status' => QuotationStatus::Draft,
                'currency' => $currency,
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'total' => $subtotal + $taxAmount,
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            foreach ($lines as $line) {
                $quotation->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'product_sku' => $line['product_sku'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $quotation->refresh()->load(['customer', 'items', 'taxRate']);
        });
    }

    /**
     * Load the quotation with its related customer, items, tax rate, and converted order.
     */
    public function find(Quotation $quotation): Quotation
    {
        return $quotation->loadMissing(['customer', 'items.product', 'taxRate', 'convertedOrder']);
    }

    /**
     * @param  array{
     *     customer_id?: int,
     *     tax_id?: int|null,
     *     notes?: string|null,
     *     valid_until?: string|null,
     *     items?: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        $this->assertStatus($quotation, QuotationStatus::Draft);

        return DB::transaction(function () use ($quotation, $data): Quotation {
            if (isset($data['customer_id'])) {
                /** @var Customer $customer */
                $customer = Customer::query()->findOrFail($data['customer_id']);

                if (! $customer->is_active) {
                    throw ValidationException::withMessages([
                        'customer_id' => ['The selected customer is inactive.'],
                    ]);
                }

                $quotation->customer_id = $customer->id;
            }

            if (array_key_exists('notes', $data)) {
                $quotation->notes = $data['notes'];
            }

            if (array_key_exists('valid_until', $data)) {
                $quotation->valid_until = $data['valid_until'];
            }

            if (array_key_exists('tax_id', $data)) {
                $quotation->tax_id = $this->resolveTax($data['tax_id'])?->id;
            }

            if (isset($data['items'])) {
                $customer = $quotation->customer ?? Customer::query()->findOrFail($quotation->customer_id);
                $lines = $this->buildLines($data['items'], $customer);
                $currency = $lines[0]['currency'];
                $subtotal = array_sum(array_column($lines, 'line_total'));

                $quotation->items()->delete();

                foreach ($lines as $line) {
                    $quotation->items()->create([
                        'product_id' => $line['product_id'],
                        'product_name' => $line['product_name'],
                        'product_sku' => $line['product_sku'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                    ]);
                }

                $quotation->currency = $currency;
                $quotation->subtotal = $subtotal;
            }

            $tax = $quotation->tax_id !== null
                ? Tax::query()->find($quotation->tax_id)
                : null;
            $quotation->tax = $tax?->calculateTax((int) $quotation->subtotal) ?? 0;
            $quotation->total = (int) $quotation->subtotal + (int) $quotation->tax;

            $quotation->save();

            return $this->find($quotation->refresh());
        });
    }

    /**
     * Delete a draft quotation.
     *
     * @throws ValidationException if the quotation is not in draft status
     */
    public function delete(Quotation $quotation): void
    {
        $this->assertStatus($quotation, QuotationStatus::Draft);
        $quotation->delete();
    }

    /**
     * Send a draft quotation to the customer.
     *
     * @throws ValidationException if the quotation is not draft or has no items
     */
    public function send(Quotation $quotation): Quotation
    {
        $this->assertStatus($quotation, QuotationStatus::Draft);

        if ($quotation->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Quotation must have at least one item before sending.'],
            ]);
        }

        $quotation->update([
            'status' => QuotationStatus::Sent,
            'sent_at' => now(),
        ]);

        return $this->find($quotation->refresh());
    }

    /**
     * @param  array{
     *     status?: string,
     *     tax_id?: int|null,
     *     warehouse_id?: int|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws Throwable
     */
    public function accept(Quotation $quotation, array $data = []): Quotation
    {
        $this->assertStatus($quotation, QuotationStatus::Sent);

        return DB::transaction(function () use ($quotation, $data): Quotation {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $quotation->loadMissing('items');

            $orderStatus = OrderStatus::tryFrom($data['status'] ?? OrderStatus::Confirmed->value)
                ?? OrderStatus::Confirmed;

            if ($orderStatus !== OrderStatus::Confirmed && $orderStatus !== OrderStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Accepted quotations can only create confirmed or pending orders.'],
                ]);
            }

            $items = $quotation->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
            ])->all();

            $order = $this->orders->create([
                'customer_id' => $quotation->customer_id,
                'tax_id' => $data['tax_id'] ?? $quotation->tax_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'notes' => $data['notes'] ?? $quotation->notes,
                'status' => $orderStatus->value,
                'items' => $items,
            ]);

            $quotation->update([
                'status' => QuotationStatus::Converted,
                'accepted_at' => now(),
                'converted_order_id' => $order->id,
            ]);

            $quotation = $this->find($quotation->refresh());

            event(new QuotationAccepted($quotation, $order, (string) $tenant->getTenantKey()));

            return $quotation;
        });
    }

    /**
     * Reject a sent quotation.
     *
     * @throws ValidationException if the quotation is not in sent status
     */
    public function reject(Quotation $quotation): Quotation
    {
        $this->assertStatus($quotation, QuotationStatus::Sent);

        $quotation->update([
            'status' => QuotationStatus::Rejected,
            'rejected_at' => now(),
        ]);

        return $this->find($quotation->refresh());
    }

    /**
     * Resolve the active tax rate by id, or fall back to the default active tax rate.
     *
     * @throws ModelNotFoundException if the given tax id does not resolve to an active tax
     */
    private function resolveTax(?int $taxId): ?Tax
    {
        if ($taxId !== null) {
            /** @var Tax $tax */
            $tax = Tax::query()->whereKey($taxId)->where('is_active', true)->firstOrFail();

            return $tax;
        }

        return Tax::query()->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array{product_id: int, product_name: string, product_sku: string, quantity: int, unit_price: int, line_total: int, currency: string}>
     */
    private function buildLines(array $items, ?Customer $customer = null): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['A quotation must include at least one item.'],
            ]);
        }

        $lines = [];
        $currency = null;
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
                    'items' => ['All quotation items must share the same currency.'],
                ]);
            }

            $quantity = $item['quantity'];
            $quote = $this->pricing->quote($product, $quantity, $customer);

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price' => $quote['unit_price'],
                'line_total' => $quote['line_total'],
                'currency' => $product->currency,
            ];
        }

        return $lines;
    }

    /**
     * Ensure the quotation is in the expected status.
     *
     * @throws ValidationException if the quotation status does not match
     */
    private function assertStatus(Quotation $quotation, QuotationStatus $expected): void
    {
        if ($quotation->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Quotation must be in {$expected->value} status."],
            ]);
        }
    }
}
