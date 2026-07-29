<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\CustomerType;
use Database\Factories\Tenant\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tenant CRM customer record.
 *
 * @property int $id
 * @property string|null $code
 * @property int|null $customer_group_id
 * @property CustomerType|null $type
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company
 * @property int|null $credit_limit
 * @property string|null $payment_terms
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
 * @property-read CustomerWallet|null $wallet
 */
#[Fillable([
    'code',
    'customer_group_id',
    'type',
    'name',
    'email',
    'phone',
    'company',
    'credit_limit',
    'payment_terms',
    'currency',
    'tax_exempt',
    'tax_id',
    'notes',
    'is_active',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tenant')
            ->logOnly(['name', 'email', 'phone', 'company', 'customer_group_id', 'type', 'credit_limit', 'payment_terms', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
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

    /**
     * @return HasOne<CustomerWallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }
}
