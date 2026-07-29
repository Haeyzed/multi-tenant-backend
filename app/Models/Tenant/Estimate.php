<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\EstimateStatus;
use Database\Factories\Tenant\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int|null $tax_id
 * @property EstimateStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $notes
 * @property Carbon|null $valid_until
 * @property int|null $converted_quotation_id
 * @property int|null $converted_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer $customer
 * @property-read Tax|null $taxRate
 * @property-read Collection<int, EstimateItem> $items
 * @property-read Quotation|null $convertedQuotation
 * @property-read Order|null $convertedOrder
 */
#[Fillable([
    'number',
    'customer_id',
    'tax_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'notes',
    'valid_until',
    'converted_quotation_id',
    'converted_order_id',
])]
class Estimate extends Model
{
    /** @use HasFactory<EstimateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EstimateStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'valid_until' => 'datetime',
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
     * @return BelongsTo<Tax, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /**
     * @return HasMany<EstimateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EstimateItem::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function convertedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'converted_quotation_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
