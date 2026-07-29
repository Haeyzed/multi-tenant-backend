<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\LeadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company
 * @property string|null $source
 * @property LeadStatus $status
 * @property int|null $owner_id
 * @property int|null $customer_id
 * @property int|null $estimated_value
 * @property string|null $currency
 * @property string|null $notes
 * @property Carbon|null $converted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'name',
    'email',
    'phone',
    'company',
    'source',
    'status',
    'owner_id',
    'customer_id',
    'estimated_value',
    'currency',
    'notes',
    'converted_at',
])]
class Lead extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'estimated_value' => 'integer',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subjectable');
    }
}
