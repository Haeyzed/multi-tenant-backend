<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SupplierInvoiceStatus;
use App\Enums\Tenant\SupplierPaymentStatus;
use App\Events\Tenant\Erp\SupplierPaymentRecorded;
use App\Models\Central\Tenant;
use App\Models\Tenant\SupplierInvoice;
use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\SupplierPaymentAllocation;
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
 * Supplier payment recording and invoice allocation.
 */
final class SupplierPaymentService
{
    /**
     * @return LengthAwarePaginator<int, SupplierPayment>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierPayment::class)
            ->with(['supplier', 'allocations.invoice'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('supplier_id'),
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
                AllowedInclude::relationship('supplier'),
                AllowedInclude::relationship('allocations'),
                AllowedInclude::relationship('creator'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function find(SupplierPayment $payment): SupplierPayment
    {
        return $payment->loadMissing(['supplier', 'allocations.invoice', 'creator']);
    }

    /**
     * @param  array{
     *     supplier_id: int,
     *     currency: string,
     *     amount: int,
     *     method: string,
     *     status?: string,
     *     reference?: string|null,
     *     notes?: string|null,
     *     paid_at?: string|null,
     *     allocations?: list<array{supplier_invoice_id: int, amount: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data): SupplierPayment {
            $status = SupplierPaymentStatus::tryFrom($data['status'] ?? SupplierPaymentStatus::Completed->value)
                ?? SupplierPaymentStatus::Completed;
            $method = SalesPaymentMethod::from($data['method']);

            /** @var SupplierPayment $payment */
            $payment = SupplierPayment::query()->create([
                'number' => 'SPAY-'.Str::upper(Str::random(10)),
                'supplier_id' => $data['supplier_id'],
                'currency' => strtoupper($data['currency']),
                'amount' => $data['amount'],
                'method' => $method,
                'status' => $status,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $status === SupplierPaymentStatus::Completed
                    ? ($data['paid_at'] ?? now())
                    : null,
                'created_by' => auth()->id(),
            ]);

            if (! empty($data['allocations'])) {
                $this->syncAllocations($payment, $data['allocations']);
            }

            if ($status === SupplierPaymentStatus::Completed) {
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
     *     allocations?: list<array{supplier_invoice_id: int, amount: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(SupplierPayment $payment, array $data): SupplierPayment
    {
        if ($payment->status === SupplierPaymentStatus::Void) {
            throw ValidationException::withMessages([
                'payment' => ['Void payments cannot be updated.'],
            ]);
        }

        return DB::transaction(function () use ($payment, $data): SupplierPayment {
            $wasCompleted = $payment->status === SupplierPaymentStatus::Completed;

            if (isset($data['status'])) {
                $status = SupplierPaymentStatus::from($data['status']);
                $payment->status = $status;

                if ($status === SupplierPaymentStatus::Completed && $payment->paid_at === null) {
                    $payment->paid_at = $data['paid_at'] ?? now();
                }

                if ($status === SupplierPaymentStatus::Void) {
                    $payment->paid_at = null;
                }
            }

            if (array_key_exists('reference', $data)) {
                $payment->reference = $data['reference'];
            }

            if (array_key_exists('notes', $data)) {
                $payment->notes = $data['notes'];
            }

            if (array_key_exists('paid_at', $data) && $payment->status === SupplierPaymentStatus::Completed) {
                $payment->paid_at = $data['paid_at'];
            }

            $payment->save();

            if (isset($data['allocations']) && $payment->status !== SupplierPaymentStatus::Void) {
                $payment->allocations()->delete();
                $this->syncAllocations($payment, $data['allocations']);
            }

            if ($payment->status === SupplierPaymentStatus::Void) {
                $this->refreshInvoiceStatuses(
                    SupplierInvoice::query()
                        ->whereIn('id', $payment->allocations()->pluck('supplier_invoice_id'))
                        ->get()
                );
            }

            if ($payment->status === SupplierPaymentStatus::Completed && ! $wasCompleted) {
                $this->dispatchPaymentRecorded($payment);
            }

            return $this->find($payment->refresh());
        });
    }

    public function delete(SupplierPayment $payment): void
    {
        if ($payment->status === SupplierPaymentStatus::Completed) {
            throw ValidationException::withMessages([
                'payment' => ['Completed payments cannot be deleted. Void them instead.'],
            ]);
        }

        $payment->delete();
    }

    /**
     * @param  list<array{supplier_invoice_id: int, amount: int}>  $allocations
     */
    private function syncAllocations(SupplierPayment $payment, array $allocations): void
    {
        $totalAllocated = 0;
        $invoiceIds = [];

        foreach ($allocations as $index => $allocation) {
            /** @var SupplierInvoice $invoice */
            $invoice = SupplierInvoice::query()->findOrFail($allocation['supplier_invoice_id']);

            if ($invoice->supplier_id !== $payment->supplier_id) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.supplier_invoice_id" => ['Invoice does not belong to the payment supplier.'],
                ]);
            }

            if ($invoice->status === SupplierInvoiceStatus::Void) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.supplier_invoice_id" => ['Cannot allocate payment to a void invoice.'],
                ]);
            }

            if ($invoice->status === SupplierInvoiceStatus::Draft) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.supplier_invoice_id" => ['Cannot allocate payment to a draft invoice.'],
                ]);
            }

            $amount = $allocation['amount'];
            $totalAllocated += $amount;

            SupplierPaymentAllocation::query()->create([
                'supplier_payment_id' => $payment->id,
                'supplier_invoice_id' => $invoice->id,
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
            SupplierInvoice::query()->whereIn('id', $invoiceIds)->get()
        );
    }

    /**
     * @param  Collection<int, SupplierInvoice>|\Illuminate\Database\Eloquent\Collection<int, SupplierInvoice>  $invoices
     */
    private function refreshInvoiceStatuses($invoices): void
    {
        foreach ($invoices as $invoice) {
            $allocated = (int) SupplierPaymentAllocation::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->whereHas('payment', fn ($query) => $query->where('status', SupplierPaymentStatus::Completed))
                ->sum('amount');

            if ($allocated >= $invoice->total && $invoice->status !== SupplierInvoiceStatus::Paid) {
                $invoice->forceFill([
                    'status' => SupplierInvoiceStatus::Paid,
                    'paid_at' => $invoice->paid_at ?? now(),
                ])->save();
            } elseif ($allocated < $invoice->total && $invoice->status === SupplierInvoiceStatus::Paid) {
                $invoice->forceFill([
                    'status' => SupplierInvoiceStatus::Issued,
                    'paid_at' => null,
                ])->save();
            }
        }
    }

    private function dispatchPaymentRecorded(SupplierPayment $payment): void
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        event(new SupplierPaymentRecorded($payment->loadMissing(['supplier', 'allocations']), (string) $tenant->getTenantKey()));
    }
}
