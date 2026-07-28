<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Named catalog of product unit prices (tenant commerce, not SaaS billing).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $currency
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, PriceListItem> $items
 * @property-read Collection<int, PriceListAssignment> $assignments
 */
#[Fillable([
    'name',
    'code',
    'currency',
    'priority',
    'is_default',
    'is_active',
    'starts_at',
    'ends_at',
])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PriceListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /**
     * @return HasMany<PriceListAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PriceListAssignment::class);
    }

    /**
     * @param  Builder<PriceList>  $query
     * @return Builder<PriceList>
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
