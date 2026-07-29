<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Events\Tenant\Erp\CustomerCreated;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            ->with(['group', 'tags'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('company'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('customer_group_id'),
                AllowedFilter::exact('tax_exempt'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('tag', function ($query, $value): void {
                    $query->whereHas('tags', fn ($q) => $q->where('customer_tags.slug', $value)->orWhere('customer_tags.id', $value));
                }),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('email'),
                AllowedSort::field('company'),
                AllowedSort::field('credit_limit'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     customer_group_id?: int|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     credit_limit?: int|null,
     *     currency?: string|null,
     *     tax_exempt?: bool,
     *     tax_id?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     tag_ids?: list<int>
     * }  $data
     */
    public function create(array $data): Customer
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateCustomer($tenant);
        $this->assertGroup($data['customer_group_id'] ?? null);

        $customer = Customer::query()->create([
            'code' => isset($data['code']) ? strtoupper($data['code']) : $this->generateCode(),
            'customer_group_id' => $data['customer_group_id'] ?? null,
            'type' => $data['type'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'tax_exempt' => $data['tax_exempt'] ?? false,
            'tax_id' => $data['tax_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['tag_ids'])) {
            $customer->tags()->sync($data['tag_ids']);
        }

        event(new CustomerCreated($customer, (string) $tenant->getTenantKey()));

        return $customer->load(['group', 'tags']);
    }

    public function find(Customer $customer): Customer
    {
        return $customer->loadCount(['orders', 'addresses', 'contacts', 'crmNotes'])
            ->loadMissing(['group', 'tags', 'addresses', 'contacts']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string|null,
     *     customer_group_id?: int|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     credit_limit?: int|null,
     *     currency?: string|null,
     *     tax_exempt?: bool,
     *     tax_id?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     tag_ids?: list<int>
     * }  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        if (array_key_exists('customer_group_id', $data)) {
            $this->assertGroup($data['customer_group_id']);
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $customer->fill($data)->save();

        if (is_array($tagIds)) {
            $customer->tags()->sync($tagIds);
        }

        return $customer->refresh()->load(['group', 'tags']);
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    private function assertGroup(?int $groupId): void
    {
        if ($groupId === null) {
            return;
        }

        if (! CustomerGroup::query()->whereKey($groupId)->exists()) {
            throw ValidationException::withMessages([
                'customer_group_id' => ['The selected customer group is invalid.'],
            ]);
        }
    }

    private function generateCode(): string
    {
        return 'CUS-'.Str::upper(Str::random(8));
    }
}
