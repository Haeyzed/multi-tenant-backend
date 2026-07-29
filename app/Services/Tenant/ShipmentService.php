<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\ShippingLabelProvider;
use App\Enums\Tenant\ShipmentStatus;
use App\Events\Tenant\Erp\ShipmentCreated;
use App\Events\Tenant\Erp\ShipmentDelivered;
use App\Models\Central\Tenant;
use App\Models\Tenant\Fulfilment;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;
use App\Models\Tenant\ShippingCarrier;
use App\Models\Tenant\ShippingMethod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Outbound shipment lifecycle.
 */
final class ShipmentService
{
    public function __construct(private ShippingLabelProvider $shippingLabelProvider) {}

    /**
     * @return LengthAwarePaginator<int, Shipment>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Shipment::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('order_id'),
                AllowedFilter::exact('fulfilment_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('number'),
                AllowedFilter::partial('tracking_number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('shipped_at'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('order'),
                AllowedInclude::relationship('fulfilment'),
                AllowedInclude::relationship('packages'),
            )
            ->defaultSort('-created_at')
            ->with(['order', 'packages'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     order_id: int,
     *     fulfilment_id?: int|null,
     *     carrier?: string|null,
     *     shipping_carrier_id?: int|null,
     *     shipping_method_id?: int|null,
     *     tracking_number?: string|null,
     *     notes?: string|null,
     *     packages?: list<array{label?: string|null, weight_grams?: int|null, dimensions?: string|null, tracking_number?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Shipment
    {
        return DB::transaction(function () use ($data): Shipment {
            /** @var Tenant $tenant */
            $tenant = tenant();

            /** @var Order $order */
            $order = Order::query()->findOrFail($data['order_id']);

            if (isset($data['fulfilment_id'])) {
                /** @var Fulfilment $fulfilment */
                $fulfilment = Fulfilment::query()->findOrFail($data['fulfilment_id']);

                if ($fulfilment->order_id !== $order->id) {
                    throw ValidationException::withMessages([
                        'fulfilment_id' => ['The fulfilment does not belong to this order.'],
                    ]);
                }
            }

            $carrierName = $data['carrier'] ?? null;
            if (isset($data['shipping_carrier_id'])) {
                $carrier = ShippingCarrier::query()->findOrFail($data['shipping_carrier_id']);
                $carrierName = $carrierName ?? $carrier->name;
            }

            if (isset($data['shipping_method_id'])) {
                $method = ShippingMethod::query()->findOrFail($data['shipping_method_id']);
                if (isset($data['shipping_carrier_id']) && $method->shipping_carrier_id !== $data['shipping_carrier_id']) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => ['The shipping method does not belong to the selected carrier.'],
                    ]);
                }
            }

            /** @var Shipment $shipment */
            $shipment = Shipment::query()->create([
                'number' => 'SHP-'.Str::upper(Str::random(10)),
                'order_id' => $order->id,
                'fulfilment_id' => $data['fulfilment_id'] ?? null,
                'status' => ShipmentStatus::Draft,
                'carrier' => $carrierName,
                'shipping_carrier_id' => $data['shipping_carrier_id'] ?? null,
                'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['packages'] ?? [] as $package) {
                ShipmentPackage::query()->create([
                    'shipment_id' => $shipment->id,
                    'label' => $package['label'] ?? null,
                    'weight_grams' => $package['weight_grams'] ?? null,
                    'dimensions' => $package['dimensions'] ?? null,
                    'tracking_number' => $package['tracking_number'] ?? null,
                ]);
            }

            $shipment = $this->find($shipment->refresh());

            event(new ShipmentCreated($shipment, (string) $tenant->getTenantKey()));

            return $shipment;
        });
    }

    public function find(Shipment $shipment): Shipment
    {
        return $shipment->loadMissing(['order', 'fulfilment', 'packages', 'shippingCarrier', 'shippingMethod']);
    }

    public function purchaseLabel(Shipment $shipment, ShipmentPackage $package): ShipmentPackage
    {
        $label = $this->shippingLabelProvider->purchaseLabel($shipment, $package);

        $package->update([
            'label_provider' => $label->provider,
            'label' => $label->label,
            'label_url' => $label->labelUrl,
            'tracking_number' => $label->trackingNumber,
            'label_payload' => $label->payload,
        ]);

        return $package->refresh();
    }

    public function dispatch(Shipment $shipment): Shipment
    {
        $this->assertStatus($shipment, ShipmentStatus::Draft);

        $shipment->update([
            'status' => ShipmentStatus::InTransit,
            'shipped_at' => now(),
        ]);

        return $this->find($shipment->refresh());
    }

    /**
     * @throws Throwable
     */
    public function deliver(Shipment $shipment): Shipment
    {
        $this->assertStatus($shipment, ShipmentStatus::InTransit);

        return DB::transaction(function () use ($shipment): Shipment {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $shipment->update([
                'status' => ShipmentStatus::Delivered,
                'delivered_at' => now(),
            ]);

            $shipment = $this->find($shipment->refresh());

            event(new ShipmentDelivered($shipment, (string) $tenant->getTenantKey()));

            return $shipment;
        });
    }

    public function cancel(Shipment $shipment): Shipment
    {
        $this->assertStatus($shipment, ShipmentStatus::Draft);

        $shipment->update(['status' => ShipmentStatus::Cancelled]);

        return $this->find($shipment->refresh());
    }

    public function delete(Shipment $shipment): void
    {
        $this->assertStatus($shipment, ShipmentStatus::Draft);
        $shipment->delete();
    }

    private function assertStatus(Shipment $shipment, ShipmentStatus $expected): void
    {
        if ($shipment->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Shipment must be in {$expected->value} status."],
            ]);
        }
    }
}
