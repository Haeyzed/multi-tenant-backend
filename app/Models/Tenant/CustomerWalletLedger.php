<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\WalletLedgerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_wallet_id
 * @property WalletLedgerType $type
 * @property int $amount
 * @property int $points
 * @property int $balance_after
 * @property int $points_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read CustomerWallet $wallet
 * @property-read Model|null $reference
 * @property-read User|null $creator
 */
#[Fillable([
    'customer_wallet_id',
    'type',
    'amount',
    'points',
    'balance_after',
    'points_after',
    'reference_type',
    'reference_id',
    'notes',
    'created_by',
])]
class CustomerWalletLedger extends Model
{
    public const ?string UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletLedgerType::class,
            'amount' => 'integer',
            'points' => 'integer',
            'balance_after' => 'integer',
            'points_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomerWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
