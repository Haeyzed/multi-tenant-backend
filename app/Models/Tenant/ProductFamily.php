<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ProductFamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description', 'is_active'])]
class ProductFamily extends Model
{
    /** @use HasFactory<ProductFamilyFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<AttributeSet, $this> */
    public function attributeSets(): HasMany
    {
        return $this->hasMany(AttributeSet::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
