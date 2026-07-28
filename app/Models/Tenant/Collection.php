<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\CollectionType;
use Database\Factories\Tenant\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Product collection (manual or smart).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property CollectionType $type
 * @property bool $is_featured
 * @property bool $is_active
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read EloquentCollection<int, CollectionRule> $rules
 * @property-read EloquentCollection<int, Product> $products
 */
#[Fillable(['name', 'slug', 'description', 'type', 'is_featured', 'is_active', 'meta_title', 'meta_description'])]
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CollectionRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(CollectionRule::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
