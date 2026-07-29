<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\ChannelAdapterKey;
use App\Enums\Tenant\ChannelType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Sales channel (web, POS, marketplace, B2B).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property ChannelType $type
 * @property ChannelAdapterKey|null $adapter
 * @property int|null $warehouse_id
 * @property bool $is_active
 * @property bool $is_default
 * @property array<string, mixed>|null $config
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'code',
    'type',
    'adapter',
    'warehouse_id',
    'is_active',
    'is_default',
    'config',
    'notes',
])]
class Channel extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'adapter' => ChannelAdapterKey::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<ChannelInventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(ChannelInventory::class);
    }

    /**
     * @return HasMany<ChannelProductPrice, $this>
     */
    public function productPrices(): HasMany
    {
        return $this->hasMany(ChannelProductPrice::class);
    }

    /**
     * @return HasMany<PosSession, $this>
     */
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
