<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\SalesInvoiceStatus;
use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SalesPaymentStatus;
use App\Events\Tenant\Erp\PaymentRecorded;
use App\Models\Central\Tenant;
use App\Models\Tenant\SalesInvoice;
use App\Models\Tenant\SalesPayment;
use App\Models\Tenant\SalesPaymentAllocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Sales payment recording and invoice allocation.
 */
final class SalesPaymentService
{
    /**
     * @return LengthAwarePaginator<int, SalesPayment>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SalesPayment::class)
            ->with(['customer', 'allocations.invoice'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('method'),
                AllowedFilter::exact('currency'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('amount'),
                AllowedSort::field('paid_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('customer'),
                AllowedInclude::relationship('allocations'),
                AllowedInclude::relationship('creator'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Load the sales payment with its related customer, allocations, and creator.
     */
    public function find(SalesPayment $payment): SalesPayment
    {
        return $payment->loadMissing(['customer', 'allocations.invoice', 'creator']);
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     currency: string,
     *     amount: int,
     *     method: string,
     *     status?: string,
     *     reference?: string|null,
     *     notes?: string|null,
     *     paid_at?: string|null,
     *     allocations?: list<array{sales_invoice_id: int, amount: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): SalesPayment
    {
        return DB::transaction(function () use ($data): SalesPayment {
            $status = SalesPaymentStatus::tryFrom($data['status'] ?? SalesPaymentStatus::Completed->value)
                ?? SalesPaymentStatus::Completed;
            $method = SalesPaymentMethod::from($data['method']);

            /** @var SalesPayment $payment */
            $payment = SalesPayment::query()->create([
                'number' => 'PAY-'.Str::upper(Str::random(10)),
                'customer_id' => $data['customer_id'],
                'currency' => strtoupper($data['currency']),
                'amount' => $data['amount'],
                'method' => $method,
                'status' => $status,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $status === SalesPaymentStatus::Completed
                    ? ($data['paid_at'] ?? now())
                    : null,
                'created_by' => auth()->id(),
            ]);

            if (! empty($data['allocations'])) {
                $this->syncAllocations($payment, $data['allocations']);
            }

            if ($status === SalesPaymentStatus::Completed) {
                $this->dispatchPaymentRecorded($payment);
            }

            return $this->find($payment->refresh());
        });
    }

    /**
     * @param  array{
     *     status?: string,
     *     reference?: string|null,
     *     notes?: string|null,
     *     paid_at?: string|null,
     *     allocations?: list<array{sales_invoice_id: int, amount: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(SalesPayment $payment, array $data): SalesPayment
    {
        if ($payment->status === SalesPaymentStatus::Void) {
            throw ValidationException::withMessages([
                'payment' => ['Void payments cannot be updated.'],
            ]);
        }

        return DB::transaction(function () use ($payment, $data): SalesPayment {
            $wasCompleted = $payment->status === SalesPaymentStatus::Completed;

            if (isset($data['status'])) {
                $status = SalesPaymentStatus::from($data['status']);
                $payment->status = $status;

                if ($status === SalesPaymentStatus::Completed && $payment->paid_at === null) {
                    $payment->paid_at = $data['paid_at'] ?? now();
                }

                if ($status === SalesPaymentStatus::Void) {
                    $payment->paid_at = null;
                }
            }

            if (array_key_exists('reference', $data)) {
                $payment->reference = $data['reference'];
            }

            if (array_key_exists('notes', $data)) {
                $payment->notes = $data['notes'];
            }

            if (array_key_exists('paid_at', $data) && $payment->status === SalesPaymentStatus::Completed) {
                $payment->paid_at = $data['paid_at'];
            }

            $payment->save();

            if (isset($data['allocations']) && $payment->status !== SalesPaymentStatus::Void) {
                $payment->allocations()->delete();
                $this->syncAllocations($payment, $data['allocations']);
            }

            if ($payment->status === SalesPaymentStatus::Void) {
                $this->refreshInvoiceStatuses(
                    SalesInvoice::query()
                        ->whereIn('id', $payment->allocations()->pluck('sales_invoice_id'))
                        ->get()
                );
            }

            if ($payment->status === SalesPaymentStatus::Completed && ! $wasCompleted) {
                $this->dispatchPaymentRecorded($payment);
            }

            return $this->find($payment->refresh());
        });
    }

    /**
     * Delete a sales payment that has not been completed.
     *
     * @throws ValidationException if the payment is completed
     */
    public function delete(SalesPayment $payment): void
    {
        if ($payment->status === SalesPaymentStatus::Completed) {
            throw ValidationException::withMessages([
                'payment' => ['Completed payments cannot be deleted. Void them instead.'],
            ]);
        }

        $payment->delete();
    }

    /**
     * @param  list<array{sales_invoice_id: int, amount: int}>  $allocations
     */
    private function syncAllocations(SalesPayment $payment, array $allocations): void
    {
        $totalAllocated = 0;
        $invoiceIds = [];

        foreach ($allocations as $index => $allocation) {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::query()->findOrFail($allocation['sales_invoice_id']);

            if ($invoice->customer_id !== $payment->customer_id) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.sales_invoice_id" => ['Invoice does not belong to the payment customer.'],
                ]);
            }

            if ($invoice->status === SalesInvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.sales_invoice_id" => ['Cannot allocate payment to a void invoice.'],
                ]);
            }

            $amount = $allocation['amount'];
            $totalAllocated += $amount;

            SalesPaymentAllocation::query()->create([
                'sales_payment_id' => $payment->id,
                'sales_invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);

            $invoiceIds[] = $invoice->id;
        }

        if ($totalAllocated > $payment->amount) {
            throw ValidationException::withMessages([
                'allocations' => ['Total allocated amount exceeds the payment amount.'],
            ]);
        }

        $this->refreshInvoiceStatuses(
            SalesInvoice::query()->whereIn('id', $invoiceIds)->get()
        );
    }

    /**
     * @param  Collection<int, SalesInvoice>|\Illuminate\Database\Eloquent\Collection<int, SalesInvoice>  $invoices
     */
    private function refreshInvoiceStatuses($invoices): void
    {
        foreach ($invoices as $invoice) {
            $allocated = (int) SalesPaymentAllocation::query()
                ->where('sales_invoice_id', $invoice->id)
                ->whereHas('payment', fn ($query) => $query->where('status', SalesPaymentStatus::Completed))
                ->sum('amount');

            if ($allocated >= $invoice->total && $invoice->status !== SalesInvoiceStatus::Paid) {
                $invoice->forceFill([
                    'status' => SalesInvoiceStatus::Paid,
                    'paid_at' => $invoice->paid_at ?? now(),
                ])->save();
            } elseif ($allocated < $invoice->total && $invoice->status === SalesInvoiceStatus::Paid) {
                $invoice->forceFill([
                    'status' => SalesInvoiceStatus::Issued,
                    'paid_at' => null,
                ])->save();
            }
        }
    }

    /**
     * Dispatch the payment recorded event for a completed sales payment.
     */
    private function dispatchPaymentRecorded(SalesPayment $payment): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        event(new PaymentRecorded($payment->loadMissing(['customer', 'allocations']), (string) $tenant->getTenantKey()));
    }
}
