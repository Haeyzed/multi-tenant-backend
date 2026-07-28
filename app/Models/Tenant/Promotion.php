<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\PromotionType;
use Database\Factories\Tenant\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant commerce promotion (separate from central SaaS coupons).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property PromotionType $type
 * @property int $value
 * @property string|null $currency
 * @property int $priority
 * @property int|null $min_subtotal
 * @property bool $stackable
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, CustomerGroup> $customerGroups
 */
#[Fillable([
    'name',
    'code',
    'type',
    'value',
    'currency',
    'priority',
    'min_subtotal',
    'stackable',
    'is_active',
    'starts_at',
    'ends_at',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'priority' => 'integer',
            'min_subtotal' => 'integer',
            'stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')->withTimestamps();
    }

    /**
     * @return BelongsToMany<CustomerGroup, $this>
     */
    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'promotion_customer_group')->withTimestamps();
    }

    /**
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeCurrentlyEffective(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($at): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $q) use ($at): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }
}
