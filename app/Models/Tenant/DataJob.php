<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\DataJobResource;
use App\Enums\Tenant\DataJobStatus;
use App\Enums\Tenant\DataJobType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property DataJobType $type
 * @property DataJobResource $resource
 * @property DataJobStatus $status
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $result
 * @property string|null $error_message
 * @property int|null $created_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'type',
    'resource',
    'status',
    'options',
    'result',
    'error_message',
    'created_by',
    'started_at',
    'finished_at',
])]
class DataJob extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DataJobType::class,
            'resource' => DataJobResource::class,
            'status' => DataJobStatus::class,
            'options' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
