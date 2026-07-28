<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\PriceListAssignmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Binds a price list to a customer, group, or future channel.
 *
 * @property int $id
 * @property int $price_list_id
 * @property PriceListAssignmentType $assignable_type
 * @property int $assignable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['price_list_id', 'assignable_type', 'assignable_id'])]
class PriceListAssignment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignable_type' => PriceListAssignmentType::class,
            'assignable_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
