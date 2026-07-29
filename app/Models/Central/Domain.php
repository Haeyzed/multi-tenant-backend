<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Hostname used to identify a tenant on the central connection.
 *
 * Domains are unique and stored lowercase. Stancl resolves the current tenant
 * from the request host via this model.
 *
 * @property int $id
 * @property string $domain Hostname (e.g. `acme.example.test`).
 * @property string $tenant_id Owning tenant UUID.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['domain', 'tenant_id'])]
class Domain extends BaseDomain
{
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('domains')
            ->logOnly(['domain', 'tenant_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
