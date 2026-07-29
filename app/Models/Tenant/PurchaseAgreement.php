<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\PurchaseAgreementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Supplier purchase agreement / contract.
 *
 * @property int $id
 * @property int $supplier_id
 * @property string $number
 * @property string $title
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string $currency
 * @property string|null $payment_terms
 * @property PurchaseAgreementStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseAgreementItem> $items
 */
#[Fillable([
    'supplier_id',
    'number',
    'title',
    'starts_at',
    'ends_at',
    'currency',
    'payment_terms',
    'status',
    'notes',
])]
class PurchaseAgreement extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => PurchaseAgreementStatus::class,
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
     * @return HasMany<PurchaseAgreementItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseAgreementItem::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
