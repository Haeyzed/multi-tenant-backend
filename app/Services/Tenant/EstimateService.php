<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\EstimateStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Estimate;
use App\Models\Tenant\Product;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Sales estimate lifecycle and conversion to quotations.
 */
final class EstimateService
{
    public function __construct(private QuotationService $quotations) {}

    /**
     * @return LengthAwarePaginator<int, Estimate>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Estimate::class)
            ->with(['customer', 'items'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
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
     *     customer_id: int,
     *     tax_id?: int|null,
     *     notes?: string|null,
     *     valid_until?: string|null,
     *     items: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Estimate
    {
        return DB::transaction(function () use ($data): Estimate {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($data['customer_id']);

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer is inactive.'],
                ]);
            }

            $lines = $this->buildLines($data['items']);
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $tax = isset($data['tax_id']) ? Tax::query()->find($data['tax_id']) : null;
            $taxAmount = $tax?->calculateTax($subtotal) ?? 0;

            /** @var Estimate $estimate */
            $estimate = Estimate::query()->create([
                'number' => 'EST-'.Str::upper(Str::random(10)),
                'customer_id' => $customer->id,
                'tax_id' => $tax?->id,
                'status' => EstimateStatus::Draft,
                'currency' => $lines[0]['currency'],
                'subtotal' => $subtotal,
                'tax' => $taxAmount,
                'total' => $subtotal + $taxAmount,
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            foreach ($lines as $line) {
                $estimate->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'product_sku' => $line['product_sku'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $this->find($estimate->refresh());
        });
    }

    /**
     * Load an estimate with its customer, items, tax rate, and converted quotation relations.
     */
    public function find(Estimate $estimate): Estimate
    {
        return $estimate->loadMissing(['customer', 'items.product', 'taxRate', 'convertedQuotation']);
    }

    /**
     * @param  array{
     *     notes?: string|null,
     *     valid_until?: string|null,
     *     tax_id?: int|null,
     *     items?: list<array{product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(Estimate $estimate, array $data): Estimate
    {
        if ($estimate->status !== EstimateStatus::Draft) {
            throw ValidationException::withMessages([
                'estimate' => ['Only draft estimates can be updated.'],
            ]);
        }

        return DB::transaction(function () use ($estimate, $data): Estimate {
            if (array_key_exists('notes', $data)) {
                $estimate->notes = $data['notes'];
            }

            if (array_key_exists('valid_until', $data)) {
                $estimate->valid_until = $data['valid_until'];
            }

            if (array_key_exists('tax_id', $data)) {
                $estimate->tax_id = $data['tax_id'];
            }

            if (isset($data['items'])) {
                $lines = $this->buildLines($data['items']);
                $estimate->items()->delete();
                foreach ($lines as $line) {
                    $estimate->items()->create([
                        'product_id' => $line['product_id'],
                        'product_name' => $line['product_name'],
                        'product_sku' => $line['product_sku'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                    ]);
                }
                $estimate->currency = $lines[0]['currency'];
                $estimate->subtotal = array_sum(array_column($lines, 'line_total'));
            }

            $tax = $estimate->tax_id !== null ? Tax::query()->find($estimate->tax_id) : null;
            $estimate->tax = $tax?->calculateTax((int) $estimate->subtotal) ?? 0;
            $estimate->total = (int) $estimate->subtotal + (int) $estimate->tax;
            $estimate->save();

            return $this->find($estimate->refresh());
        });
    }

    /**
     * Delete an estimate, provided it has not already been converted.
     *
     * @throws ValidationException if the estimate has been converted to a quotation
     */
    public function delete(Estimate $estimate): void
    {
        if ($estimate->status === EstimateStatus::Converted) {
            throw ValidationException::withMessages([
                'estimate' => ['Converted estimates cannot be deleted.'],
            ]);
        }

        $estimate->delete();
    }

    /**
     * Mark a draft (or already sent) estimate as sent.
     *
     * @throws ValidationException if the estimate is not draft or sent
     */
    public function send(Estimate $estimate): Estimate
    {
        if (! in_array($estimate->status, [EstimateStatus::Draft, EstimateStatus::Sent], true)) {
            throw ValidationException::withMessages([
                'estimate' => ['Only draft estimates can be sent.'],
            ]);
        }

        $estimate->forceFill(['status' => EstimateStatus::Sent])->save();

        return $this->find($estimate->refresh());
    }

    /**
     * Convert an estimate into a quotation, linking the two records.
     *
     * @throws ValidationException if the estimate cannot be converted
     * @throws Throwable
     */
    public function convertToQuotation(Estimate $estimate): Quotation
    {
        return DB::transaction(function () use ($estimate): Quotation {
            if (in_array($estimate->status, [EstimateStatus::Converted, EstimateStatus::Rejected, EstimateStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'estimate' => ['This estimate cannot be converted.'],
                ]);
            }

            $estimate->loadMissing('items');

            $quotation = $this->quotations->create([
                'customer_id' => $estimate->customer_id,
                'tax_id' => $estimate->tax_id,
                'notes' => $estimate->notes,
                'valid_until' => $estimate->valid_until?->toDateTimeString(),
                'items' => $estimate->items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->all(),
            ]);

            $estimate->forceFill([
                'status' => EstimateStatus::Converted,
                'converted_quotation_id' => $quotation->id,
            ])->save();

            return $quotation;
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @return list<array{product_id: int, product_name: string, product_sku: string, quantity: int, unit_price: int, line_total: int, currency: string}>
     */
    private function buildLines(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['An estimate must include at least one item.'],
            ]);
        }

        $lines = [];

        foreach ($items as $item) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($item['product_id']);
            $quantity = (int) $item['quantity'];
            $unitPrice = (int) $product->unit_price;

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'currency' => $product->currency,
            ];
        }

        return $lines;
    }
}
