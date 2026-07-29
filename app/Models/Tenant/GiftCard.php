<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\GiftCardStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string|null $pin
 * @property int $balance_initial
 * @property int $balance_remaining
 * @property string $currency
 * @property GiftCardStatus $status
 * @property int|null $issued_to
 * @property Carbon|null $expires_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer|null $customer
 * @property-read User|null $creator
 * @property-read Collection<int, GiftCardRedemption> $redemptions
 */
#[Fillable([
    'code',
    'pin',
    'balance_initial',
    'balance_remaining',
    'currency',
    'status',
    'issued_to',
    'expires_at',
    'notes',
    'created_by',
])]
class GiftCard extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GiftCardStatus::class,
            'balance_initial' => 'integer',
            'balance_remaining' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'issued_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<GiftCardRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }
}
