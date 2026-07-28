<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Events\Tenant\Erp\CustomerCreated;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant CRM customer management.
 */
final class CustomerService
{
    public function __construct(private EntitlementEnforcer $entitlements) {}

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Customer::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('company'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('email'),
                AllowedSort::field('company'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, company?: string|null, notes?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): Customer
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateCustomer($tenant);

        $customer = Customer::query()->create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        event(new CustomerCreated($customer, (string) $tenant->getTenantKey()));

        return $customer;
    }

    public function find(Customer $customer): Customer
    {
        return $customer->loadCount('orders');
    }

    /**
     * @param  array{name?: string, email?: string|null, phone?: string|null, company?: string|null, notes?: string|null, is_active?: bool}  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->fill($data)->save();

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
