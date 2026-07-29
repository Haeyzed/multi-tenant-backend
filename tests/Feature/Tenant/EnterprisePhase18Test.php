<?php

declare(strict_types=1);

use App\Contracts\Tenant\PushSender;
use App\Contracts\Tenant\ShippingLabelProvider;
use App\Contracts\Tenant\SmsSender;
use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\ChannelAdapterKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\ShipmentStatus;
use App\Models\Tenant;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Central\FeatureFlagService;
use App\Services\Tenant\ChannelAdapters\AmazonChannelAdapter;
use App\Services\Tenant\Notifications\FcmPushSender;
use App\Services\Tenant\Notifications\TwilioSmsSender;
use App\Services\Tenant\Shipping\EasyPostShippingLabelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse}
 */
function phase18Context(string $domain = 'phase18.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpCatalogueAdvanced, true);
    $flags->set(FeatureFlagKey::ErpChannels, true);
    $flags->set(FeatureFlagKey::ErpReturnsShipping, true);
    $flags->set(FeatureFlagKey::ErpSalesAdvanced, true);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, false);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $warehouse = $tenant->run(function (): Warehouse {
        return Warehouse::factory()->create([
            'code' => 'P18',
            'is_default' => true,
            'is_active' => true,
        ]);
    });

    return [$tenant, $token, $warehouse];
}

it('resolves live integration drivers from config', function (): void {
    config([
        'services.sms.driver' => 'twilio',
        'services.push.driver' => 'fcm',
        'services.shipping_label.driver' => 'easypost',
    ]);

    expect(app(SmsSender::class))->toBeInstanceOf(TwilioSmsSender::class)
        ->and(app(PushSender::class))->toBeInstanceOf(FcmPushSender::class)
        ->and(app(ShippingLabelProvider::class))->toBeInstanceOf(EasyPostShippingLabelProvider::class);
});

it('purchases an easypost label via http fake', function (): void {
    config([
        'services.shipping_label.driver' => 'easypost',
        'services.easypost.api_key' => 'test_easypost_key',
    ]);

    Http::fake([
        'api.easypost.com/v2/shipments' => Http::response([
            'id' => 'shp_123',
            'rates' => [['id' => 'rate_123']],
        ], 200),
        'api.easypost.com/v2/shipments/shp_123/buy' => Http::response([
            'id' => 'shp_123',
            'tracking_code' => 'EZ123',
            'postage_label' => ['label_url' => 'https://easypost.test/label.png'],
            'tracker' => ['tracking_code' => 'EZ123'],
        ], 200),
    ]);

    [$tenant, $token] = phase18Context('p18-labels.localhost');

    [$shipmentId, $packageId] = $tenant->run(function (): array {
        $customer = Customer::factory()->create(['is_active' => true]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed,
            'placed_at' => now(),
        ]);

        $shipment = Shipment::query()->create([
            'number' => 'SHP-P18',
            'order_id' => $order->id,
            'status' => ShipmentStatus::Draft,
            'carrier' => 'easypost',
        ]);

        $package = ShipmentPackage::query()->create([
            'shipment_id' => $shipment->id,
            'weight_grams' => 500,
        ]);

        return [$shipment->id, $package->id];
    });

    $this->withToken($token)
        ->postJson('http://p18-labels.localhost/api/shipments/'.$shipmentId.'/packages/'.$packageId.'/purchase-label')
        ->assertSuccessful()
        ->assertJsonPath('data.label_provider', 'easypost')
        ->assertJsonPath('data.label_url', 'https://easypost.test/label.png')
        ->assertJsonPath('data.tracking_number', 'EZ123');

    $tenant->delete();
});

it('pulls marketplace orders over http with channel credentials', function (): void {
    Http::fake([
        'sellingpartnerapi-na.amazon.com/*' => Http::response([
            'payload' => [
                'Orders' => [
                    ['AmazonOrderId' => 'A1'],
                    ['AmazonOrderId' => 'A2'],
                ],
            ],
        ], 200),
    ]);

    $adapter = app(AmazonChannelAdapter::class);
    $channel = new Channel([
        'name' => 'Amazon',
        'adapter' => ChannelAdapterKey::Amazon,
        'config' => [
            'client_id' => 'amz-client',
            'client_secret' => 'amz-secret',
            'access_token' => 'amz-token',
            'access_token_expires_at' => now()->addHour()->toIso8601String(),
            'marketplace_id' => 'ATVPDKIKX0DER',
        ],
    ]);

    expect($adapter->pullOrders($channel))->toBe(2);
});

it('manages product translations and overlays locale on show', function (): void {
    [$tenant, $token] = phase18Context('p18-i18n.localhost');

    $productId = $tenant->run(fn (): int => Product::factory()->create([
        'sku' => 'TR-1',
        'name' => 'English Name',
        'slug' => 'english-name',
        'currency' => 'USD',
        'unit_price' => 1000,
        'track_inventory' => false,
    ])->id);

    $this->withToken($token)
        ->putJson('http://p18-i18n.localhost/api/products/'.$productId.'/translations/fr', [
            'name' => 'Nom Francais',
            'slug' => 'nom-francais',
            'description' => 'Description FR',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.locale', 'fr')
        ->assertJsonPath('data.name', 'Nom Francais');

    $this->withToken($token)
        ->getJson('http://p18-i18n.localhost/api/products/'.$productId.'?locale=fr')
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Nom Francais')
        ->assertJsonPath('data.slug', 'nom-francais')
        ->assertJsonPath('data.locale', 'fr');

    $this->withToken($token)
        ->getJson('http://p18-i18n.localhost/api/products/'.$productId.'/translations')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $tenant->delete();
});
