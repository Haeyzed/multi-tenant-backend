<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\CustomerAddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property CustomerAddressType $type
 * @property string|null $label
 * @property string|null $contact_name
 * @property string $line1
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property string|null $phone
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier $supplier
 */
#[Fillable([
    'supplier_id',
    'type',
    'label',
    'contact_name',
    'line1',
    'line2',
    'city',
    'state',
    'postal_code',
    'country',
    'phone',
    'is_default',
])]
class SupplierAddress extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomerAddressType::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
