<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Central tenant record that owns a dedicated database and domain hostnames.
 *
 * Stored on the central connection. Creating or deleting a tenant triggers
 * Stancl tenancy lifecycle jobs (database provisioning / teardown). Soft deletes
 * are intentionally omitted: Eloquent soft-delete would still fire Stancl
 * teardown listeners and drop the tenant database.
 *
 * @property string $id Tenant UUID primary key.
 * @property string|null $name Human-readable display name.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Domain> $domains
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read Subscription|null $currentSubscription
 *
 * @method Domain createDomain(array<string, mixed>|string $data)
 */
#[Fillable(['id', 'name'])]
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasDomains, HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Columns stored as real table columns instead of the Stancl `data` JSON blob.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'created_at',
            'updated_at',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tenants')
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Latest entitling subscription for the tenant, if any.
     *
     * @return HasOne<Subscription, $this>
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->entitling()
            ->latestOfMany();
    }
}
