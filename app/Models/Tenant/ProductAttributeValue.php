<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Attribute value assigned to a product.
 *
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property int|null $attribute_value_id
 * @property string|null $value_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Attribute $attribute
 * @property-read AttributeValue|null $attributeValue
 */
#[Fillable(['product_id', 'attribute_id', 'attribute_value_id', 'value_text'])]
class ProductAttributeValue extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * @return BelongsTo<AttributeValue, $this>
     */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }
}
