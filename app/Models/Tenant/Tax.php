<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\TaxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant sales tax configuration.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $rate_bps
 * @property bool $is_inclusive
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'code', 'rate_bps', 'is_inclusive', 'is_default', 'is_active'])]
class Tax extends Model
{
    /** @use HasFactory<TaxFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'is_inclusive' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Additive tax amount for an exclusive tax rate (0 when tax-inclusive).
     */
    public function calculateTax(int $subtotal): int
    {
        if ($this->is_inclusive || $subtotal <= 0) {
            return 0;
        }

        return (int) round($subtotal * $this->rate_bps / 10000);
    }
}
