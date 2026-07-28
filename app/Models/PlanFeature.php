<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\FeatureKey;
use Database\Factories\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Feature / limit row attached to a {@see Plan}.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $feature_key
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan $plan
 */
#[Fillable(['plan_id', 'feature_key', 'value'])]
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): ?FeatureKey
    {
        return FeatureKey::tryFrom($this->feature_key);
    }
}
