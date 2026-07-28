<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Tenant\Erp\CustomerCreated;
use App\Events\Tenant\Erp\OrderConfirmed;
use App\Events\Tenant\Erp\ProductCreated;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Bridges tenant ERP domain events into central audit/integration channels.
 */
class LogTenantErpActivity implements ShouldQueue
{
    public function handleCustomerCreated(CustomerCreated $event): void
    {
        $this->log('customer.created', $event->tenantId, [
            'customer_id' => $event->customer->id,
            'name' => $event->customer->name,
            'email' => $event->customer->email,
        ]);
    }

    public function handleProductCreated(ProductCreated $event): void
    {
        $this->log('product.created', $event->tenantId, [
            'product_id' => $event->product->id,
            'sku' => $event->product->sku,
            'name' => $event->product->name,
        ]);
    }

    public function handleOrderConfirmed(OrderConfirmed $event): void
    {
        $this->log('order.confirmed', $event->tenantId, [
            'order_id' => $event->order->id,
            'number' => $event->order->number,
            'total' => $event->order->total,
            'currency' => $event->order->currency,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(string $eventName, string $tenantId, array $properties): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant !== null) {
            activity()
                ->performedOn($tenant)
                ->event($eventName)
                ->withProperties(array_merge(['tenant_id' => $tenantId], $properties))
                ->log($eventName);
        }

        Log::info('tenant.erp.'.$eventName, array_merge(['tenant_id' => $tenantId], $properties));
    }
}
