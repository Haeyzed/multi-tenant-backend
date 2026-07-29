<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\CustomerAddressType;
use App\Enums\Tenant\CustomerNoteType;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\CustomerContact;
use App\Models\Tenant\CustomerNote;
use App\Models\Tenant\CustomerTag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Nested CRM resources for enterprise customers.
 */
final class CustomerCrmService
{
    /**
     * @return LengthAwarePaginator<int, CustomerAddress>
     */
    public function listAddresses(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CustomerAddress::class)
            ->where('customer_id', $customer->id)
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
    public function createAddress(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $address = CustomerAddress::query()->create([
                'customer_id' => $customer->id,
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
                $this->unsetOtherDefaultAddresses($customer, $address);
            }

            return $address;
        });
    }

    /**
     * @param  array{type?: string, label?: string|null, contact_name?: string|null, line1?: string, line2?: string|null, city?: string|null, state?: string|null, postal_code?: string|null, country?: string|null, phone?: string|null, is_default?: bool}  $data
     *
     * @throws Throwable
     */
    public function updateAddress(Customer $customer, CustomerAddress $address, array $data): CustomerAddress
    {
        $this->assertBelongsToCustomer($address->customer_id, $customer->id);

        return DB::transaction(function () use ($customer, $address, $data): CustomerAddress {
            if (isset($data['country'])) {
                $data['country'] = strtoupper($data['country']);
            }

            $address->fill($data)->save();

            if ($address->is_default) {
                $this->unsetOtherDefaultAddresses($customer, $address);
            }

            return $address->refresh();
        });
    }

    /**
     * Delete a customer address.
     */
    public function deleteAddress(Customer $customer, CustomerAddress $address): void
    {
        $this->assertBelongsToCustomer($address->customer_id, $customer->id);
        $address->delete();
    }

    /**
     * @return LengthAwarePaginator<int, CustomerContact>
     */
    public function listContacts(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CustomerContact::class)
            ->where('customer_id', $customer->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('is_primary'),
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
    public function createContact(Customer $customer, array $data): CustomerContact
    {
        return DB::transaction(function () use ($customer, $data): CustomerContact {
            $contact = CustomerContact::query()->create([
                'customer_id' => $customer->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'title' => $data['title'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($contact->is_primary) {
                $this->unsetOtherPrimaryContacts($customer, $contact);
            }

            return $contact;
        });
    }

    /**
     * @param  array{name?: string, email?: string|null, phone?: string|null, title?: string|null, is_primary?: bool, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function updateContact(Customer $customer, CustomerContact $contact, array $data): CustomerContact
    {
        $this->assertBelongsToCustomer($contact->customer_id, $customer->id);

        return DB::transaction(function () use ($customer, $contact, $data): CustomerContact {
            $contact->fill($data)->save();

            if ($contact->is_primary) {
                $this->unsetOtherPrimaryContacts($customer, $contact);
            }

            return $contact->refresh();
        });
    }

    /**
     * Delete a customer contact.
     */
    public function deleteContact(Customer $customer, CustomerContact $contact): void
    {
        $this->assertBelongsToCustomer($contact->customer_id, $customer->id);
        $contact->delete();
    }

    /**
     * @return LengthAwarePaginator<int, CustomerNote>
     */
    public function listNotes(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CustomerNote::class)
            ->where('customer_id', $customer->id)
            ->with('author')
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('type'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{type?: string, subject?: string|null, body: string}  $data
     */
    public function createNote(Customer $customer, array $data): CustomerNote
    {
        return CustomerNote::query()->create([
            'customer_id' => $customer->id,
            'type' => $data['type'] ?? CustomerNoteType::General->value,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'created_by' => auth()->id(),
        ])->load('author');
    }

    /**
     * @param  array{type?: string, subject?: string|null, body?: string}  $data
     */
    public function updateNote(Customer $customer, CustomerNote $note, array $data): CustomerNote
    {
        $this->assertBelongsToCustomer($note->customer_id, $customer->id);
        $note->fill($data)->save();

        return $note->refresh()->load('author');
    }

    /**
     * Delete a customer note.
     */
    public function deleteNote(Customer $customer, CustomerNote $note): void
    {
        $this->assertBelongsToCustomer($note->customer_id, $customer->id);
        $note->delete();
    }

    /**
     * @return LengthAwarePaginator<int, CustomerTag>
     */
    public function listTags(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CustomerTag::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('slug'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('slug'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, slug?: string, color?: string|null}  $data
     */
    public function createTag(array $data): CustomerTag
    {
        return CustomerTag::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);
    }

    /**
     * @param  array{name?: string, slug?: string, color?: string|null}  $data
     */
    public function updateTag(CustomerTag $tag, array $data): CustomerTag
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tag->fill($data)->save();

        return $tag->refresh();
    }

    /**
     * Delete a customer tag.
     */
    public function deleteTag(CustomerTag $tag): void
    {
        $tag->delete();
    }

    /**
     * @param  list<int>  $tagIds
     */
    public function syncTags(Customer $customer, array $tagIds): Customer
    {
        $validIds = CustomerTag::query()->whereIn('id', $tagIds)->pluck('id')->all();

        if (count($validIds) !== count(array_unique($tagIds))) {
            throw ValidationException::withMessages([
                'tag_ids' => ['One or more tags are invalid.'],
            ]);
        }

        $customer->tags()->sync($validIds);

        return $customer->load('tags');
    }

    /**
     * Clear the default flag on every other address of the same type for this customer.
     */
    private function unsetOtherDefaultAddresses(Customer $customer, CustomerAddress $address): void
    {
        CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->where('type', $address->type)
            ->whereKeyNot($address->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Clear the primary flag on every other contact for this customer.
     */
    private function unsetOtherPrimaryContacts(Customer $customer, CustomerContact $contact): void
    {
        CustomerContact::query()
            ->where('customer_id', $customer->id)
            ->whereKeyNot($contact->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Ensure a nested CRM resource belongs to the given customer.
     */
    private function assertBelongsToCustomer(int $ownerId, int $customerId): void
    {
        if ($ownerId !== $customerId) {
            abort(404);
        }
    }
}
