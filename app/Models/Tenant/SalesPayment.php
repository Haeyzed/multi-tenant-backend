<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SalesPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property string $currency
 * @property int $amount
 * @property SalesPaymentMethod $method
 * @property SalesPaymentStatus $status
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon|null $paid_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer $customer
 * @property-read Collection<int, SalesPaymentAllocation> $allocations
 * @property-read User|null $creator
 */
#[Fillable([
    'number',
    'customer_id',
    'currency',
    'amount',
    'method',
    'status',
    'reference',
    'notes',
    'paid_at',
    'created_by',
])]
class SalesPayment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => SalesPaymentMethod::class,
            'status' => SalesPaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SalesPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SalesPaymentAllocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
