<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

use App\DataTransferObjects\Tenant\ShippingLabelResult;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;

interface ShippingLabelProvider
{
    public function purchaseLabel(Shipment $shipment, ShipmentPackage $package): ShippingLabelResult;
}
