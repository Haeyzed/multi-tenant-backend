<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SupplierRfqStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $number
 * @property int|null $purchase_request_id
 * @property SupplierRfqStatus $status
 * @property string|null $notes
 * @property Carbon|null $sent_at
 * @property Carbon|null $closes_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read PurchaseRequest|null $purchaseRequest
 * @property-read User|null $creator
 * @property-read Collection<int, SupplierRfqItem> $items
 * @property-read Collection<int, SupplierQuote> $quotes
 */
#[Fillable([
    'number',
    'purchase_request_id',
    'status',
    'notes',
    'sent_at',
    'closes_at',
    'created_by',
])]
class SupplierRfq extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tenant')
            ->logOnly(['number', 'status', 'notes', 'sent_at', 'closes_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierRfqStatus::class,
            'sent_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SupplierRfqItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierRfqItem::class);
    }

    /**
     * @return HasMany<SupplierQuote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(SupplierQuote::class);
    }
}
