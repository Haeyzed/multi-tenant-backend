<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant CRM customer record.
 *
 * @property int $id
 * @property string|null $code
 * @property int|null $customer_group_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company
 * @property int|null $credit_limit
 * @property string|null $currency
 * @property bool $tax_exempt
 * @property string|null $tax_id
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CustomerGroup|null $group
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, CustomerAddress> $addresses
 * @property-read Collection<int, CustomerContact> $contacts
 * @property-read Collection<int, CustomerNote> $crmNotes
 * @property-read Collection<int, CustomerTag> $tags
 */
#[Fillable([
    'code',
    'customer_group_id',
    'name',
    'email',
    'phone',
    'company',
    'credit_limit',
    'currency',
    'tax_exempt',
    'tax_id',
    'notes',
    'is_active',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'tax_exempt' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CustomerGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasMany<CustomerContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * @return HasMany<CustomerNote, $this>
     */
    public function crmNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * @return BelongsToMany<CustomerTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_tag')->withTimestamps();
    }
}
