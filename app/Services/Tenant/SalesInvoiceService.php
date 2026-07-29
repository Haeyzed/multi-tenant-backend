<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\SalesInvoiceStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\SalesInvoice;
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
 * Tenant sales invoices generated from confirmed orders.
 */
final class SalesInvoiceService
{
    /**
     * @return LengthAwarePaginator<int, SalesInvoice>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SalesInvoice::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('order_id'),
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
                AllowedSort::field('issued_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('customer'),
                AllowedInclude::relationship('order'),
            )
            ->defaultSort('-created_at')
            ->with(['customer', 'order'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Load the sales invoice with its related customer and order items.
     */
    public function find(SalesInvoice $invoice): SalesInvoice
    {
        return $invoice->loadMissing(['customer', 'order.items']);
    }

    /**
     * Create an issued sales invoice for a confirmed/fulfilled order when missing.
     *
     * @throws Throwable
     */
    public function ensureForOrder(Order $order): SalesInvoice
    {
        $existing = SalesInvoice::query()->where('order_id', $order->id)->first();

        if ($existing !== null) {
            return $existing->loadMissing(['customer', 'order']);
        }

        if ($order->status !== OrderStatus::Confirmed && $order->status !== OrderStatus::Fulfilled) {
            throw ValidationException::withMessages([
                'order' => ['Sales invoices can only be created for confirmed or fulfilled orders.'],
            ]);
        }

        return DB::transaction(function () use ($order): SalesInvoice {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::query()->create([
                'number' => 'SINV-'.Str::upper(Str::random(10)),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'status' => SalesInvoiceStatus::Issued,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'total' => $order->total,
                'notes' => $order->notes,
                'issued_at' => now(),
            ]);

            return $invoice->load(['customer', 'order']);
        });
    }

    /**
     * @param  array{status?: string, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function update(SalesInvoice $invoice, array $data): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data): SalesInvoice {
            if ($invoice->status === SalesInvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    'invoice' => ['Void invoices cannot be updated.'],
                ]);
            }

            if (array_key_exists('notes', $data)) {
                $invoice->notes = $data['notes'];
            }

            if (isset($data['status'])) {
                $status = SalesInvoiceStatus::from($data['status']);

                if ($status === SalesInvoiceStatus::Paid) {
                    if ($invoice->status === SalesInvoiceStatus::Void) {
                        throw ValidationException::withMessages([
                            'status' => ['Void invoices cannot be marked paid.'],
                        ]);
                    }

                    $invoice->status = SalesInvoiceStatus::Paid;
                    $invoice->paid_at ??= now();
                } elseif ($status === SalesInvoiceStatus::Void) {
                    if ($invoice->status === SalesInvoiceStatus::Paid) {
                        throw ValidationException::withMessages([
                            'status' => ['Paid invoices cannot be voided.'],
                        ]);
                    }

                    $invoice->status = SalesInvoiceStatus::Void;
                } else {
                    throw ValidationException::withMessages([
                        'status' => ['Only paid or void transitions are supported.'],
                    ]);
                }
            }

            $invoice->save();

            return $invoice->refresh()->load(['customer', 'order']);
        });
    }

    /**
     * Delete a sales invoice.
     */
    public function delete(SalesInvoice $invoice): void
    {
        $invoice->delete();
    }
}
