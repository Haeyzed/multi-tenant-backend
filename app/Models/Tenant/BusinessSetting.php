<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\BusinessSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tenant business configuration key/value store.
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
class BusinessSetting extends Model
{
    /** @use HasFactory<BusinessSettingFactory> */
    use HasFactory;

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
