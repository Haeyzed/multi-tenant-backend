<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\CustomerGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Customer segment for pricing and credit defaults.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $discount_percent
 * @property int|null $price_list_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Customer> $customers
 */
#[Fillable(['name', 'code', 'description', 'discount_percent', 'price_list_id', 'is_active'])]
class CustomerGroup extends Model
{
    /** @use HasFactory<CustomerGroupFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'price_list_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
