<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gift_card_id
 * @property int|null $order_id
 * @property int $amount
 * @property int $balance_before
 * @property int $balance_after
 * @property int|null $redeemed_by
 * @property Carbon|null $created_at
 * @property-read GiftCard $giftCard
 * @property-read Order|null $order
 * @property-read User|null $redeemer
 */
#[Fillable([
    'gift_card_id',
    'order_id',
    'amount',
    'balance_before',
    'balance_after',
    'redeemed_by',
    'created_at',
])]
class GiftCardRedemption extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GiftCard, $this>
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }
}
