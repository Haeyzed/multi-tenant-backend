<?php

declare(strict_types=1);

namespace App\Services\Tenant\Shipping;

use App\Contracts\Tenant\ShippingLabelProvider;
use App\DataTransferObjects\Tenant\ShippingLabelResult;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EasyPostShippingLabelProvider implements ShippingLabelProvider
{
    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function purchaseLabel(Shipment $shipment, ShipmentPackage $package): ShippingLabelResult
    {
        $apiKey = config('services.easypost.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('EasyPost API key is not configured.');
        }

        $shipmentPayload = [
            'shipment' => [
                'to_address' => [
                    'name' => 'Recipient',
                    'street1' => '417 Montgomery St',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'zip' => '94104',
                    'country' => 'US',
                ],
                'from_address' => [
                    'name' => 'Sender',
                    'street1' => '417 Montgomery St',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'zip' => '94104',
                    'country' => 'US',
                ],
                'parcel' => [
                    'weight' => max(1, (int) ($package->weight_grams ?? 100)) / 28.3495,
                ],
                'reference' => $shipment->number,
            ],
        ];

        $created = Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->asJson()
            ->post('https://api.easypost.com/v2/shipments', $shipmentPayload)
            ->throw()
            ->json();

        $shipmentId = $created['id'] ?? null;
        $rateId = $created['rates'][0]['id'] ?? null;

        if (! is_string($shipmentId) || $shipmentId === '' || ! is_string($rateId) || $rateId === '') {
            throw new RuntimeException('EasyPost did not return a purchasable rate.');
        }

        $bought = Http::withBasicAuth($apiKey, '')
            ->acceptJson()
            ->asJson()
            ->post("https://api.easypost.com/v2/shipments/{$shipmentId}/buy", [
                'rate' => ['id' => $rateId],
            ])
            ->throw()
            ->json();

        $postage = is_array($bought['postage_label'] ?? null) ? $bought['postage_label'] : [];
        $tracker = is_array($bought['tracker'] ?? null) ? $bought['tracker'] : [];

        return new ShippingLabelResult(
            provider: 'easypost',
            label: is_string($bought['id'] ?? null) ? $bought['id'] : $package->label,
            labelUrl: is_string($postage['label_url'] ?? null) ? $postage['label_url'] : null,
            trackingNumber: is_string($tracker['tracking_code'] ?? null)
                ? $tracker['tracking_code']
                : (is_string($bought['tracking_code'] ?? null) ? $bought['tracking_code'] : $package->tracking_number),
            payload: is_array($bought) ? $bought : [],
        );
    }
}
