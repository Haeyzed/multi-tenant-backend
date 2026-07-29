<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $tenant_id
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property BillingGateway $gateway
 * @property string|null $gateway_payment_id
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Tenant $tenant
 */
#[Fillable([
    'invoice_id',
    'tenant_id',
    'amount',
    'currency',
    'status',
    'gateway',
    'gateway_payment_id',
    'paid_at',
    'meta',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'gateway' => BillingGateway::class,
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
