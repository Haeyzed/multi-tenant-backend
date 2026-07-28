<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant product catalog entry.
 *
 * Prices are stored in the currency's minor units (e.g. cents).
 * `stock_quantity` is null when inventory is not tracked (unlimited).
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string $currency
 * @property int $unit_price
 * @property int|null $stock_quantity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read Collection<int, WarehouseStock> $warehouseStocks
 */
#[Fillable(['category_id', 'sku', 'name', 'description', 'currency', 'unit_price', 'stock_quantity', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<WarehouseStock, $this>
     */
    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }
}
