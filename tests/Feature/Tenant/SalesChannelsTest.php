<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\ChannelAdapterKey;
use App\Enums\Tenant\ChannelType;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PosSessionStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Central\FeatureFlagService;
use App\Services\Tenant\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Product, 4: Customer}
 */
function channelsContext(string $domain = 'channels.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouses, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPricing, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpChannels, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product, $customer] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'CH',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'CH-1',
            'name' => 'Channel Product',
            'currency' => 'USD',
            'unit_price' => 1000,
            'track_inventory' => true,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        app(StockLedgerService::class)->move(
            warehouse: $warehouse,
            product: $product,
            quantityDelta: 20,
            reason: StockMovementReason::OpeningBalance,
        );

        $customer = Customer::factory()->create([
            'name' => 'Channel Buyer',
            'email' => 'buyer@channel.test',
            'is_active' => true,
        ]);

        return [$warehouse, $product, $customer];
    });

    return [$tenant, $token, $warehouse, $product, $customer];
}

it('applies channel price overrides on orders and syncs POS inventory', function (): void {
    [$tenant, $token, $warehouse, $product, $customer] = channelsContext();

    $channel = $this->withToken($token)
        ->postJson('http://channels.localhost/api/channels', [
            'name' => 'Web Store',
            'code' => 'WEB',
            'type' => ChannelType::Web->value,
            'adapter' => ChannelAdapterKey::None->value,
            'warehouse_id' => $warehouse->id,
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'WEB')
        ->json('data');

    $this->withToken($token)
        ->postJson('http://channels.localhost/api/channels/'.$channel['id'].'/prices', [
            'product_id' => $product->id,
            'unit_price' => 750,
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->assertJsonPath('data.unit_price', 750);

    $this->withToken($token)
        ->postJson('http://channels.localhost/api/channels/'.$channel['id'].'/inventories', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'buffer_quantity' => 5,
            'is_published' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.buffer_quantity', 5);

    $this->withToken($token)
        ->postJson('http://channels.localhost/api/orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'channel_id' => $channel['id'],
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.channel_id', $channel['id'])
        ->assertJsonPath('data.items.0.unit_price', 750)
        ->assertJsonPath('data.subtotal', 1500);

    $posChannel = $this->withToken($token)
        ->postJson('http://channels.localhost/api/channels', [
            'name' => 'Front Register',
            'code' => 'POS1',
            'type' => ChannelType::Pos->value,
            'adapter' => ChannelAdapterKey::Pos->value,
            'warehouse_id' => $warehouse->id,
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->postJson('http://channels.localhost/api/channels/'.$posChannel['id'].'/publish-product', [
            'product_id' => $product->id,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://channels.localhost/api/channels/'.$posChannel['id'].'/sync-inventory')
        ->assertSuccessful()
        ->assertJsonPath('data.synced', fn ($n): bool => $n >= 1);

    $tenant->delete();
});

it('opens a POS session, records a sale, and closes the session', function (): void {
    [$tenant, $token, $warehouse, $product, $customer] = channelsContext('pos.localhost');

    $channel = $this->withToken($token)
        ->postJson('http://pos.localhost/api/channels', [
            'name' => 'POS Lane',
            'code' => 'POSL',
            'type' => ChannelType::Pos->value,
            'adapter' => ChannelAdapterKey::Pos->value,
            'warehouse_id' => $warehouse->id,
        ])
        ->assertCreated()
        ->json('data');

    $session = $this->withToken($token)
        ->postJson('http://pos.localhost/api/pos-sessions', [
            'channel_id' => $channel['id'],
            'opening_float' => 10000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PosSessionStatus::Open->value)
        ->assertJsonPath('data.number', fn (string $n): bool => str_starts_with($n, 'POS-'))
        ->json('data');

    $this->withToken($token)
        ->postJson('http://pos.localhost/api/pos-sessions/'.$session['id'].'/sale', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.channel_id', $channel['id'])
        ->assertJsonPath('data.pos_session_id', $session['id'])
        ->assertJsonPath('data.status', OrderStatus::Confirmed->value);

    $this->withToken($token)
        ->postJson('http://pos.localhost/api/pos-sessions/'.$session['id'].'/close', [
            'closing_float' => 11000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', PosSessionStatus::Closed->value);

    $tenant->delete();
});

it('blocks channel endpoints when the feature flag is disabled', function (): void {
    [$tenant, $token] = channelsContext('channels-flag.localhost');
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpChannels, false);

    $this->withToken($token)
        ->getJson('http://channels-flag.localhost/api/channels')
        ->assertForbidden();

    $this->withToken($token)
        ->getJson('http://channels-flag.localhost/api/pos-sessions')
        ->assertForbidden();

    $tenant->delete();
});
