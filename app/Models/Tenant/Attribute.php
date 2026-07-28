<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product attribute definition.
 *
 * @property int $id
 * @property int|null $attribute_group_id
 * @property string $name
 * @property string $code
 * @property string $input_type
 * @property bool $is_filterable
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AttributeGroup|null $group
 * @property-read Collection<int, AttributeValue> $values
 */
#[Fillable(['attribute_group_id', 'name', 'code', 'input_type', 'is_filterable', 'position'])]
class Attribute extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AttributeGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, 'attribute_group_id');
    }

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }
}
