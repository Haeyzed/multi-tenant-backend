<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\PosSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Point-of-sale register session.
 *
 * @property int $id
 * @property string $number
 * @property int $channel_id
 * @property int|null $opened_by
 * @property int|null $closed_by
 * @property PosSessionStatus $status
 * @property int $opening_float
 * @property int|null $closing_float
 * @property string|null $notes
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'channel_id',
    'opened_by',
    'closed_by',
    'status',
    'opening_float',
    'closing_float',
    'notes',
    'opened_at',
    'closed_at',
])]
class PosSession extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PosSessionStatus::class,
            'opening_float' => 'integer',
            'closing_float' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
