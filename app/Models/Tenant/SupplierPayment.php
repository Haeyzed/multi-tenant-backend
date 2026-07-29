<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SupplierPaymentStatus;
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
 * @property int $supplier_id
 * @property string $currency
 * @property int $amount
 * @property SalesPaymentMethod $method
 * @property SupplierPaymentStatus $status
 * @property string|null $reference
 * @property string|null $notes
 * @property Carbon|null $paid_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, SupplierPaymentAllocation> $allocations
 * @property-read User|null $creator
 */
#[Fillable([
    'number',
    'supplier_id',
    'currency',
    'amount',
    'method',
    'status',
    'reference',
    'notes',
    'paid_at',
    'created_by',
])]
class SupplierPayment extends Model
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
            'status' => SupplierPaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
