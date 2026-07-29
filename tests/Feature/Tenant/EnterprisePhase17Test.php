<?php

declare(strict_types=1);

use App\Contracts\Tenant\ChannelAdapter;
use App\Contracts\Tenant\SmsSender;
use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\ChannelAdapterKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\ShipmentStatus;
use App\Models\Tenant;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\ShipmentPackage;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Tenant\TenantErpNotification;
use App\Services\Central\FeatureFlagService;
use App\Services\Tenant\ChannelAdapters\MarketplaceChannelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse}
 */
function phase17Context(string $domain = 'phase17.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpSalesAdvanced, true);
    $flags->set(FeatureFlagKey::ErpReturnsShipping, true);
    $flags->set(FeatureFlagKey::ErpChannels, true);
    $flags->set(FeatureFlagKey::ErpNotifications, true);
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
            'code' => 'P17',
            'is_default' => true,
            'is_active' => true,
        ]);
    });

    return [$tenant, $token, $warehouse];
}

it('purchases a shipment package label via the manual provider', function (): void {
    [$tenant, $token] = phase17Context('p17-labels.localhost');

    [$shipmentId, $packageId] = $tenant->run(function (): array {
        $customer = Customer::factory()->create(['is_active' => true]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed,
            'placed_at' => now(),
        ]);

        $shipment = Shipment::query()->create([
            'number' => 'SHP-P17',
            'order_id' => $order->id,
            'status' => ShipmentStatus::Draft,
            'carrier' => 'manual',
        ]);

        $package = ShipmentPackage::query()->create([
            'shipment_id' => $shipment->id,
            'label' => 'LABEL-ABC',
            'tracking_number' => 'TRACK-123',
            'weight_grams' => 500,
        ]);

        return [$shipment->id, $package->id];
    });

    $this->withToken($token)
        ->postJson('http://p17-labels.localhost/api/shipments/'.$shipmentId.'/packages/'.$packageId.'/purchase-label')
        ->assertSuccessful()
        ->assertJsonPath('data.label_provider', 'manual')
        ->assertJsonPath('data.label', 'LABEL-ABC');

    $tenant->delete();
});

it('includes sms channel when sms driver is configured', function (): void {
    config(['services.sms.driver' => 'log', 'services.push.driver' => 'null']);

    $notification = new TenantErpNotification('Hello', 'World');
    $user = new class
    {
        public string $email = 'a@b.test';

        public string $phone = '+15555550100';
    };

    $channels = $notification->via($user);

    expect($channels)->toContain(SmsChannel::class);

    $sender = Mockery::mock(SmsSender::class);
    $sender->shouldReceive('send')->once()->with('+15555550100', 'Hello: World');
    app()->instance(SmsSender::class, $sender);

    app(SmsChannel::class)->send($user, $notification);
});

it('marketplace adapters expose pullOrders as a no-op stub', function (): void {
    $adapter = new MarketplaceChannelAdapter(ChannelAdapterKey::Amazon->value);
    expect($adapter)->toBeInstanceOf(ChannelAdapter::class);

    $channel = new Channel(['name' => 'Amazon', 'adapter' => ChannelAdapterKey::Amazon->value]);
    expect($adapter->pullOrders($channel))->toBe(0);

    $adapter->acknowledgeOrder($channel, 'ext-1');
});
