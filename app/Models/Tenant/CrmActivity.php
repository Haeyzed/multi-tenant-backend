<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\CrmActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property CrmActivityType $type
 * @property string|null $subject
 * @property string|null $body
 * @property string $subjectable_type
 * @property int $subjectable_id
 * @property int|null $user_id
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'type',
    'subject',
    'body',
    'subjectable_type',
    'subjectable_id',
    'user_id',
    'due_at',
    'completed_at',
])]
class CrmActivity extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CrmActivityType::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subjectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
