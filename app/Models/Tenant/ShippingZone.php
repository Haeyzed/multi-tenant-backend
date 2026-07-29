<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Geographic shipping zone for rate methods.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property list<string>|null $countries
 * @property list<string>|null $postal_codes
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'code',
    'countries',
    'postal_codes',
    'is_active',
    'notes',
])]
class ShippingZone extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'postal_codes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ShippingMethod, $this>
     */
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
