<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PriceListAssignmentType;
use App\Enums\Tenant\PromotionType;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Services\Central\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Product, 3: Customer}
 */
function pricingContext(string $domain = 'pricing.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPricing, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCustomersAdvanced, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$product, $customer] = $tenant->run(function (): array {
        $product = Product::factory()->create([
            'sku' => 'PRICE-1',
            'name' => 'Priced Product',
            'currency' => 'USD',
            'unit_price' => 1000,
            'is_active' => true,
            'track_inventory' => false,
            'stock_quantity' => null,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Price Buyer',
            'email' => 'buyer@price.test',
            'is_active' => true,
        ]);

        return [$product, $customer];
    });

    return [$tenant, $token, $product, $customer];
}

it('resolves price list and promotion via preview and orders', function (): void {
    [$tenant, $token, $product, $customer] = pricingContext();

    $listId = $this->withToken($token)
        ->postJson('http://pricing.localhost/api/price-lists', [
            'name' => 'Wholesale',
            'code' => 'WHL',
            'currency' => 'USD',
            'is_default' => true,
            'items' => [
                ['product_id' => $product->id, 'unit_price' => 800, 'min_quantity' => 1],
                ['product_id' => $product->id, 'unit_price' => 700, 'min_quantity' => 5],
            ],
            'assignments' => [
                [
                    'assignable_type' => PriceListAssignmentType::Customer->value,
                    'assignable_id' => $customer->id,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'WHL')
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://pricing.localhost/api/promotions', [
            'name' => 'Spring 10',
            'code' => 'SPRING10',
            'type' => PromotionType::PercentOff->value,
            'value' => 10,
            'product_ids' => [$product->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'SPRING10');

    $this->withToken($token)
        ->postJson('http://pricing.localhost/api/pricing/preview', [
            'product_id' => $product->id,
            'quantity' => 5,
            'customer_id' => $customer->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.catalog_unit_price', 1000)
        ->assertJsonPath('data.list_unit_price', 700)
        ->assertJsonPath('data.unit_price', 630)
        ->assertJsonPath('data.price_list_id', $listId)
        ->assertJsonPath('data.promotion_code', 'SPRING10');

    $order = $this->withToken($token)
        ->postJson('http://pricing.localhost/api/orders', [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Draft->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ])
        ->assertCreated();

    expect($order->json('data.items.0.unit_price'))->toBe(630)
        ->and($order->json('data.subtotal'))->toBe(3150);

    $tenant->delete();
});

it('applies customer group discount when no list price exists', function (): void {
    [$tenant, $token, $product, $customer] = pricingContext('pricing-group.localhost');

    $groupId = $tenant->run(function (): int {
        return CustomerGroup::factory()->create([
            'code' => 'VIP',
            'discount_percent' => 20,
            'price_list_id' => null,
        ])->id;
    });

    $this->withToken($token)
        ->putJson('http://pricing-group.localhost/api/customers/'.$customer->id, [
            'customer_group_id' => $groupId,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://pricing-group.localhost/api/pricing/preview', [
            'product_id' => $product->id,
            'quantity' => 1,
            'customer_id' => $customer->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.unit_price', 800)
        ->assertJsonPath('data.group_discount_percent', 20)
        ->assertJsonPath('data.list_unit_price', null);

    $tenant->delete();
});

it('blocks pricing APIs when the feature flag is disabled', function (): void {
    [$tenant, $token] = pricingContext('pricing-flag.localhost');
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPricing, false);

    $this->withToken($token)
        ->getJson('http://pricing-flag.localhost/api/price-lists')
        ->assertForbidden();

    $tenant->delete();
});
