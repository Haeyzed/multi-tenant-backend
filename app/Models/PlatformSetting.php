<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Key/value platform configuration stored on the central connection.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string $group
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value', 'type', 'group', 'description'])]
class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use CentralConnection, HasFactory;

    /**
     * Decode the stored value according to its type.
     */
    public function decodedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
