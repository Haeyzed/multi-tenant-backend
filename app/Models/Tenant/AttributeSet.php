<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\AttributeSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'product_family_id', 'description', 'is_active'])]
class AttributeSet extends Model
{
    /** @use HasFactory<AttributeSetFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<ProductFamily, $this> */
    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }

    /** @return BelongsToMany<Attribute, $this> */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_set_attributes')
            ->withPivot(['position', 'is_required'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
