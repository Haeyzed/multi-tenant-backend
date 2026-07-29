<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Enums\Tenant\Role;
use App\Events\Tenant\Erp\PaymentRecorded;
use App\Events\Tenant\Erp\PurchaseRequestApproved;
use App\Events\Tenant\Erp\StockCountPosted;
use App\Events\Tenant\Erp\SupplierInvoiceIssued;
use App\Events\Tenant\Erp\SupplierPaymentRecorded;
use App\Models\Tenant\User;
use App\Services\Tenant\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyOnErpEvent implements ShouldQueue
{
    public function __construct(private NotificationService $notifications) {}

    public function handlePurchaseRequestApproved(PurchaseRequestApproved $event): void
    {
        $this->notify(
            title: 'Purchase request approved',
            body: "Purchase request {$event->purchaseRequest->number} was approved.",
            data: [
                'type' => 'purchase_request_approved',
                'purchase_request_id' => $event->purchaseRequest->id,
            ],
        );
    }

    public function handlePaymentRecorded(PaymentRecorded $event): void
    {
        $this->notify(
            title: 'Payment recorded',
            body: "Payment {$event->payment->number} was recorded.",
            data: [
                'type' => 'payment_recorded',
                'sales_payment_id' => $event->payment->id,
            ],
        );
    }

    public function handleStockCountPosted(StockCountPosted $event): void
    {
        $this->notify(
            title: 'Stock count posted',
            body: "Stock count {$event->stockCount->number} was posted.",
            data: [
                'type' => 'stock_count_posted',
                'stock_count_id' => $event->stockCount->id,
                'warehouse_id' => $event->stockCount->warehouse_id,
            ],
        );
    }

    public function handleSupplierPaymentRecorded(SupplierPaymentRecorded $event): void
    {
        $this->notify(
            title: 'Supplier payment recorded',
            body: "Supplier payment {$event->payment->number} was recorded.",
            data: [
                'type' => 'supplier_payment_recorded',
                'supplier_payment_id' => $event->payment->id,
            ],
        );
    }

    public function handleSupplierInvoiceIssued(SupplierInvoiceIssued $event): void
    {
        $this->notify(
            title: 'Supplier invoice issued',
            body: "Supplier invoice {$event->invoice->number} was issued.",
            data: [
                'type' => 'supplier_invoice_issued',
                'supplier_invoice_id' => $event->invoice->id,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(string $title, string $body, array $data): void
    {
        $user = auth()->user();

        if ($user instanceof User) {
            $this->notifications->notifyUser($user, $title, $body, $data);

            return;
        }

        User::role(Role::Admin->value)->get()->each(
            function (User $admin) use ($title, $body, $data): void {
                $this->notifications->notifyUser($admin, $title, $body, $data);
            },
        );
    }
}
