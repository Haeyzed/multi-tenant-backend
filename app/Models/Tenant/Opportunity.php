<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\OpportunityStage;
use App\Enums\Tenant\OpportunityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string $name
 * @property int|null $lead_id
 * @property int|null $customer_id
 * @property int|null $owner_id
 * @property OpportunityStage $stage
 * @property OpportunityStatus $status
 * @property int $amount
 * @property string $currency
 * @property int $probability
 * @property Carbon|null $expected_close_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'name',
    'lead_id',
    'customer_id',
    'owner_id',
    'stage',
    'status',
    'amount',
    'currency',
    'probability',
    'expected_close_at',
    'closed_at',
    'notes',
])]
class Opportunity extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'status' => OpportunityStatus::class,
            'amount' => 'integer',
            'probability' => 'integer',
            'expected_close_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subjectable');
    }
}
