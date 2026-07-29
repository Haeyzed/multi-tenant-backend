<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\EstimateStatus;
use App\Models\Tenant;
use App\Models\Tenant\Attribute;
use App\Models\Tenant\AttributeGroup;
use App\Models\Tenant\Customer;
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
 * @return array{0: Tenant, 1: string}
 */
function phase16Context(string $domain = 'phase16.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpCatalogueAdvanced, true);
    $flags->set(FeatureFlagKey::ErpSalesAdvanced, true);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, false);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages product families and attribute sets', function (): void {
    [$tenant, $token] = phase16Context('p16-families.localhost');

    $family = $this->withToken($token)
        ->postJson('http://p16-families.localhost/api/product-families', [
            'name' => 'Apparel',
            'code' => 'APPAREL',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'APPAREL')
        ->json('data');

    $attributeId = $tenant->run(function (): int {
        $group = AttributeGroup::query()->create([
            'name' => 'Specs',
            'code' => 'SPECS',
            'position' => 0,
        ]);

        return Attribute::query()->create([
            'attribute_group_id' => $group->id,
            'name' => 'Color',
            'code' => 'COLOR',
            'input_type' => 'text',
            'is_filterable' => true,
            'position' => 0,
        ])->id;
    });

    $set = $this->withToken($token)
        ->postJson('http://p16-families.localhost/api/attribute-sets', [
            'name' => 'Apparel Defaults',
            'code' => 'APPAREL_SET',
            'product_family_id' => $family['id'],
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->putJson('http://p16-families.localhost/api/attribute-sets/'.$set['id'].'/attributes', [
            'attributes' => [
                ['attribute_id' => $attributeId, 'position' => 0, 'is_required' => true],
            ],
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://p16-families.localhost/api/products', [
            'name' => 'Tee',
            'sku' => 'TEE-1',
            'currency' => 'USD',
            'unit_price' => 2000,
            'product_family_id' => $family['id'],
            'attribute_set_id' => $set['id'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.product_family_id', $family['id'])
        ->assertJsonPath('data.attribute_set_id', $set['id']);

    $tenant->delete();
});

it('supports seasonal collection windows', function (): void {
    [$tenant, $token] = phase16Context('p16-seasonal.localhost');

    $this->withToken($token)
        ->postJson('http://p16-seasonal.localhost/api/collections', [
            'name' => 'Summer Sale',
            'slug' => 'summer-sale',
            'type' => 'manual',
            'is_active' => true,
            'starts_at' => now()->subDay()->toDateTimeString(),
            'ends_at' => now()->addDays(10)->toDateTimeString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_in_season', true);

    $tenant->delete();
});

it('creates estimates and converts them to quotations', function (): void {
    [$tenant, $token] = phase16Context('p16-estimates.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'is_active' => true,
    ])->id);

    $productId = $tenant->run(fn (): int => Product::factory()->create([
        'sku' => 'EST-1',
        'name' => 'Estimate Item',
        'currency' => 'USD',
        'unit_price' => 2500,
        'track_inventory' => false,
    ])->id);

    $estimate = $this->withToken($token)
        ->postJson('http://p16-estimates.localhost/api/estimates', [
            'customer_id' => $customerId,
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', EstimateStatus::Draft->value)
        ->json('data');

    $this->withToken($token)
        ->postJson('http://p16-estimates.localhost/api/estimates/'.$estimate['id'].'/send')
        ->assertSuccessful()
        ->assertJsonPath('data.status', EstimateStatus::Sent->value);

    $this->withToken($token)
        ->postJson('http://p16-estimates.localhost/api/estimates/'.$estimate['id'].'/convert-to-quotation')
        ->assertCreated()
        ->assertJsonPath('data.customer_id', $customerId);

    $this->withToken($token)
        ->getJson('http://p16-estimates.localhost/api/estimates/'.$estimate['id'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', EstimateStatus::Converted->value);

    $tenant->delete();
});
