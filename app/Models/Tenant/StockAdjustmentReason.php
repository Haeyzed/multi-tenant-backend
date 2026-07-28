<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Catalog of reasons for inventory adjustments.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $increases_stock
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'code', 'increases_stock', 'is_active'])]
class StockAdjustmentReason extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'increases_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
