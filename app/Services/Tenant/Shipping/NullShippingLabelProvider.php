<?php

declare(strict_types=1);

namespace App\Services\Tenant\Shipping;

use App\Contracts\Tenant\ShippingLabelProvider;
use App\DataTransferObjects\Tenant\ShippingLabelResult;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;

final class NullShippingLabelProvider implements ShippingLabelProvider
{
    /**
     * No-op label purchase; returns the package's existing (unset) label data unchanged.
     */
    public function purchaseLabel(Shipment $shipment, ShipmentPackage $package): ShippingLabelResult
    {
        return new ShippingLabelResult(
            provider: 'null',
            label: $package->label,
            labelUrl: $package->label_url,
            trackingNumber: $package->tracking_number,
            payload: $package->label_payload ?? [],
        );
    }
}
