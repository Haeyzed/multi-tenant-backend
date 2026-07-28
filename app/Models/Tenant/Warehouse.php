<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\WarehouseType;
use Database\Factories\Tenant\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant inventory warehouse.
 *
 * @property int $id
 * @property int|null $branch_id
 * @property int|null $manager_user_id
 * @property string $name
 * @property string $code
 * @property WarehouseType $type
 * @property string|null $address
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Branch|null $branch
 * @property-read User|null $manager
 * @property-read Collection<int, WarehouseStock> $stocks
 * @property-read Collection<int, WarehouseZone> $zones
 * @property-read Collection<int, WarehouseBin> $bins
 */
#[Fillable(['branch_id', 'manager_user_id', 'name', 'code', 'type', 'address', 'is_default', 'is_active'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WarehouseType::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * @return HasMany<WarehouseStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    /**
     * @return HasMany<WarehouseZone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class);
    }

    /**
     * @return HasMany<WarehouseBin, $this>
     */
    public function bins(): HasMany
    {
        return $this->hasMany(WarehouseBin::class);
    }
}
