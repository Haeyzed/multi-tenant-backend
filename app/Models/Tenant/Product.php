<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use Database\Factories\Tenant\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property ProductType $type
 * @property ProductStatus $status
 * @property int|null $brand_id
 * @property int|null $parent_id
 * @property int|null $unit_of_measure_id
 * @property string $sku
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $currency
 * @property int $unit_price
 * @property int|null $stock_quantity
 * @property bool $track_inventory
 * @property string|null $gtin
 * @property string|null $barcode
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_keywords
 * @property bool $is_active
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 * @property-read Brand|null $brand
 * @property-read Product|null $parent
 * @property-read EloquentCollection<int, Product> $variants
 * @property-read EloquentCollection<int, ProductOption> $options
 * @property-read EloquentCollection<int, Category> $categories
 * @property-read EloquentCollection<int, Collection> $collections
 * @property-read EloquentCollection<int, ProductAttributeValue> $attributeValues
 * @property-read UnitOfMeasure|null $unitOfMeasure
 * @property-read EloquentCollection<int, ProductRelation> $productRelations
 * @property-read EloquentCollection<int, ProductOptionValue> $optionValues
 * @property-read EloquentCollection<int, OrderItem> $orderItems
 * @property-read EloquentCollection<int, WarehouseStock> $warehouseStocks
 */
#[Fillable([
    'category_id',
    'type',
    'status',
    'brand_id',
    'parent_id',
    'unit_of_measure_id',
    'sku',
    'name',
    'slug',
    'description',
    'currency',
    'unit_price',
    'stock_quantity',
    'track_inventory',
    'gtin',
    'barcode',
    'meta_title',
    'meta_description',
    'meta_keywords',
    'is_active',
    'published_at',
    'scheduled_at',
])]
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
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'unit_price' => 'integer',
            'stock_quantity' => 'integer',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
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
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ProductOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * @return HasMany<ProductRelation, $this>
     */
    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_option_value_product');
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
