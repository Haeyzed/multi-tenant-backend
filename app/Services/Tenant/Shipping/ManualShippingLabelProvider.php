<?php

declare(strict_types=1);

namespace App\Services\Tenant\Shipping;

use App\Contracts\Tenant\ShippingLabelProvider;
use App\DataTransferObjects\Tenant\ShippingLabelResult;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;

final class ManualShippingLabelProvider implements ShippingLabelProvider
{
    /**
     * Build a label result from data already recorded manually on the package.
     */
    public function purchaseLabel(Shipment $shipment, ShipmentPackage $package): ShippingLabelResult
    {
        return new ShippingLabelResult(
            provider: 'manual',
            label: $package->label,
            labelUrl: $package->label_url,
            trackingNumber: $package->tracking_number,
            payload: $package->label_payload ?? [],
        );
    }
}
