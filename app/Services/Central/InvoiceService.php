<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Invoice and payment history for tenants.
 */
final class InvoiceService
{
    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function listForTenant(Tenant $tenant, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Invoice::query()->where('tenant_id', $tenant->getTenantKey()))
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('due_at'),
                AllowedSort::field('total'),
            )
            ->defaultSort('-created_at')
            ->with(['payments', 'subscription.plan'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function find(Invoice $invoice): Invoice
    {
        return $invoice->loadMissing(['payments', 'subscription.plan', 'coupon']);
    }

    public function findForTenant(Tenant $tenant, Invoice $invoice): Invoice
    {
        abort_unless($invoice->tenant_id === $tenant->getTenantKey(), 404);

        return $this->find($invoice);
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    public function listPaymentsForTenant(Tenant $tenant, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Payment::query()->where('tenant_id', $tenant->getTenantKey()))
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('gateway'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('amount'),
            )
            ->defaultSort('-created_at')
            ->with(['invoice'])
            ->paginate($perPage)
            ->appends(request()->query());
    }
}
