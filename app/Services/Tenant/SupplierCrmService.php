<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\CustomerAddressType;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierAddress;
use App\Models\Tenant\SupplierContact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Supplier contacts and addresses.
 */
final class SupplierCrmService
{
    /**
     * @return LengthAwarePaginator<int, SupplierContact>
     */
    public function listContacts(Supplier $supplier, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierContact::class)
            ->where('supplier_id', $supplier->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('is_primary'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-is_primary')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, title?: string|null, is_primary?: bool, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function createContact(Supplier $supplier, array $data): SupplierContact
    {
        return DB::transaction(function () use ($supplier, $data): SupplierContact {
            $contact = SupplierContact::query()->create([
                'supplier_id' => $supplier->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'title' => $data['title'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($contact->is_primary) {
                $this->unsetOtherPrimaryContacts($supplier, $contact);
            }

            return $contact;
        });
    }

    /**
     * @param  array{name?: string, email?: string|null, phone?: string|null, title?: string|null, is_primary?: bool, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function updateContact(Supplier $supplier, SupplierContact $contact, array $data): SupplierContact
    {
        $this->assertBelongs($contact->supplier_id, $supplier->id);

        return DB::transaction(function () use ($supplier, $contact, $data): SupplierContact {
            $contact->fill($data)->save();

            if ($contact->is_primary) {
                $this->unsetOtherPrimaryContacts($supplier, $contact);
            }

            return $contact->refresh();
        });
    }

    public function deleteContact(Supplier $supplier, SupplierContact $contact): void
    {
        $this->assertBelongs($contact->supplier_id, $supplier->id);
        $contact->delete();
    }

    /**
     * @return LengthAwarePaginator<int, SupplierAddress>
     */
    public function listAddresses(Supplier $supplier, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierAddress::class)
            ->where('supplier_id', $supplier->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_default'),
                AllowedFilter::partial('city'),
                AllowedFilter::exact('country'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('type'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-is_default')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{type?: string, label?: string|null, contact_name?: string|null, line1: string, line2?: string|null, city?: string|null, state?: string|null, postal_code?: string|null, country?: string|null, phone?: string|null, is_default?: bool}  $data
     *
     * @throws Throwable
     */
    public function createAddress(Supplier $supplier, array $data): SupplierAddress
    {
        return DB::transaction(function () use ($supplier, $data): SupplierAddress {
            $address = SupplierAddress::query()->create([
                'supplier_id' => $supplier->id,
                'type' => $data['type'] ?? CustomerAddressType::Shipping->value,
                'label' => $data['label'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'line1' => $data['line1'],
                'line2' => $data['line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => isset($data['country']) ? strtoupper($data['country']) : null,
                'phone' => $data['phone'] ?? null,
                'is_default' => $data['is_default'] ?? false,
            ]);

            if ($address->is_default) {
                $this->unsetOtherDefaultAddresses($supplier, $address);
            }

            return $address;
        });
    }

    /**
     * @param  array{type?: string, label?: string|null, contact_name?: string|null, line1?: string, line2?: string|null, city?: string|null, state?: string|null, postal_code?: string|null, country?: string|null, phone?: string|null, is_default?: bool}  $data
     *
     * @throws Throwable
     */
    public function updateAddress(Supplier $supplier, SupplierAddress $address, array $data): SupplierAddress
    {
        $this->assertBelongs($address->supplier_id, $supplier->id);

        return DB::transaction(function () use ($supplier, $address, $data): SupplierAddress {
            if (isset($data['country'])) {
                $data['country'] = strtoupper($data['country']);
            }

            $address->fill($data)->save();

            if ($address->is_default) {
                $this->unsetOtherDefaultAddresses($supplier, $address);
            }

            return $address->refresh();
        });
    }

    public function deleteAddress(Supplier $supplier, SupplierAddress $address): void
    {
        $this->assertBelongs($address->supplier_id, $supplier->id);
        $address->delete();
    }

    private function unsetOtherPrimaryContacts(Supplier $supplier, SupplierContact $contact): void
    {
        SupplierContact::query()
            ->where('supplier_id', $supplier->id)
            ->whereKeyNot($contact->id)
            ->update(['is_primary' => false]);
    }

    private function unsetOtherDefaultAddresses(Supplier $supplier, SupplierAddress $address): void
    {
        SupplierAddress::query()
            ->where('supplier_id', $supplier->id)
            ->whereKeyNot($address->id)
            ->update(['is_default' => false]);
    }

    private function assertBelongs(int $actualSupplierId, int $expectedSupplierId): void
    {
        if ($actualSupplierId !== $expectedSupplierId) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Resource does not belong to this supplier.'],
            ]);
        }
    }
}
