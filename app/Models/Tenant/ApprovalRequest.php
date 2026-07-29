<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\ApprovalRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string $type
 * @property string $approvable_type
 * @property int $approvable_id
 * @property ApprovalRequestStatus $status
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property string|null $request_notes
 * @property string|null $decision_notes
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'type',
    'approvable_type',
    'approvable_id',
    'status',
    'requested_by',
    'decided_by',
    'request_notes',
    'decision_notes',
    'decided_at',
])]
class ApprovalRequest extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
