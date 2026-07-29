<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Order;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Order $resource
 *
 * @mixin Order
 */
#[SchemaName('Order')]
class OrderResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'tax_id' => $this->tax_id,
            'warehouse_id' => $this->warehouse_id,
            'channel_id' => $this->channel_id,
            'pos_session_id' => $this->pos_session_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'inventory_decremented' => $this->inventory_decremented,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'tax_rate' => new TaxResource($this->whenLoaded('taxRate')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'channel' => new ChannelResource($this->whenLoaded('channel')),
            'pos_session' => new PosSessionResource($this->whenLoaded('posSession')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'sales_invoice' => new SalesInvoiceResource($this->whenLoaded('salesInvoice')),
            'fulfilments_count' => $this->when(isset($this->fulfilments_count), $this->fulfilments_count),
            'shipments_count' => $this->when(isset($this->shipments_count), $this->shipments_count),
            'order_notes_count' => $this->when(isset($this->order_notes_count), $this->order_notes_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
