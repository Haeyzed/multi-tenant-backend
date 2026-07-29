<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Tenant\Erp\ApprovalDecided;
use App\Events\Tenant\Erp\OrderConfirmed;
use App\Events\Tenant\Erp\WorkOrderCompleted;
use App\Services\Tenant\WebhookService;

/**
 * Fan selected ERP domain events out to tenant webhook endpoints.
 */
final class DispatchTenantWebhooks
{
    public function __construct(private WebhookService $webhooks) {}

    public function handleOrderConfirmed(OrderConfirmed $event): void
    {
        $this->webhooks->dispatch('order.confirmed', [
            'order_id' => $event->order->id,
            'number' => $event->order->number,
            'total' => $event->order->total,
            'tenant_id' => $event->tenantId,
        ]);
    }

    public function handleWorkOrderCompleted(WorkOrderCompleted $event): void
    {
        $this->webhooks->dispatch('work_order.completed', [
            'work_order_id' => $event->workOrder->id,
            'number' => $event->workOrder->number,
            'product_id' => $event->workOrder->product_id,
            'quantity' => $event->workOrder->quantity,
            'tenant_id' => $event->tenantId,
        ]);
    }

    public function handleApprovalDecided(ApprovalDecided $event): void
    {
        $this->webhooks->dispatch('approval.decided', [
            'approval_request_id' => $event->approvalRequest->id,
            'number' => $event->approvalRequest->number,
            'status' => $event->approvalRequest->status->value,
            'type' => $event->approvalRequest->type,
            'tenant_id' => $event->tenantId,
        ]);
    }
}
