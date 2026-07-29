<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\LandedCostType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property LandedCostType $type
 * @property int $amount
 * @property string|null $currency
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['goods_receipt_id', 'type', 'amount', 'currency', 'notes'])]
class LandedCostComponent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LandedCostType::class,
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
