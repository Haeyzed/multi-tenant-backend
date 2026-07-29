<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $supplier_group_id
 * @property string $name
 * @property string $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company
 * @property string|null $currency
 * @property string|null $tax_id
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read SupplierGroup|null $group
 * @property-read Collection<int, SupplierProduct> $products
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 */
#[Fillable([
    'supplier_group_id',
    'name',
    'code',
    'email',
    'phone',
    'company',
    'currency',
    'tax_id',
    'notes',
    'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupplierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SupplierGroup::class, 'supplier_group_id');
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * @return HasMany<SupplierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /**
     * @return HasMany<SupplierAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
