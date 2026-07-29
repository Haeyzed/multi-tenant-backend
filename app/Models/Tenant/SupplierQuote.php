<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SupplierQuoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_rfq_id
 * @property int $supplier_id
 * @property SupplierQuoteStatus $status
 * @property string $currency
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $valid_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read SupplierRfq $rfq
 * @property-read Supplier $supplier
 * @property-read Collection<int, SupplierQuoteItem> $items
 */
#[Fillable([
    'supplier_rfq_id',
    'supplier_id',
    'status',
    'currency',
    'notes',
    'submitted_at',
    'valid_until',
])]
class SupplierQuote extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierQuoteStatus::class,
            'submitted_at' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SupplierRfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SupplierRfq::class, 'supplier_rfq_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<SupplierQuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteItem::class);
    }
}
