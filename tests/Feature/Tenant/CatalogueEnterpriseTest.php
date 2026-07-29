<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\CollectionType;
use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Collection;
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
function catalogueTenantContext(string $domain = 'catalogue.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCatalogueAdvanced, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages brands and product catalogue fields', function (): void {
    [$tenant, $token] = catalogueTenantContext();

    $brandId = $this->withToken($token)
        ->postJson('http://catalogue.localhost/api/brands', [
            'name' => 'Acme',
            'slug' => 'acme',
            'description' => 'Acme brand',
        ])
        ->assertCreated()
        ->json('data.id');

    $productResponse = $this->withToken($token)
        ->postJson('http://catalogue.localhost/api/products', [
            'sku' => 'CFG-1',
            'name' => 'Configurable Shirt',
            'unit_price' => 2500,
            'type' => ProductType::Configurable->value,
            'status' => ProductStatus::Published->value,
            'brand_id' => $brandId,
            'meta_title' => 'Shirt SEO',
            'gtin' => '1234567890123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', ProductType::Configurable->value)
        ->assertJsonPath('data.brand_id', $brandId)
        ->assertJsonPath('data.meta_title', 'Shirt SEO');

    expect($productResponse->json('data.slug'))->toBeString()->not->toBeEmpty();

    $tenant->run(function () use ($brandId): void {
        expect(Brand::query()->whereKey($brandId)->exists())->toBeTrue()
            ->and(Product::query()->where('sku', 'CFG-1')->value('gtin'))->toBe('1234567890123');
    });

    $tenant->delete();
});

it('creates product options and variants', function (): void {
    [$tenant, $token] = catalogueTenantContext('variants.localhost');

    $productId = $this->withToken($token)
        ->postJson('http://variants.localhost/api/products', [
            'sku' => 'PARENT-1',
            'name' => 'Parent Tee',
            'unit_price' => 2000,
            'type' => ProductType::Configurable->value,
        ])
        ->assertCreated()
        ->json('data.id');

    $option = $this->withToken($token)
        ->postJson('http://variants.localhost/api/products/'.$productId.'/options', [
            'name' => 'Size',
            'values' => ['S', 'M', 'L'],
        ])
        ->assertCreated()
        ->json('data');

    $valueId = collect($option['values'] ?? [])->firstWhere('value', 'M')['id'] ?? null;

    expect($valueId)->not->toBeNull();

    $this->withToken($token)
        ->postJson('http://variants.localhost/api/products/'.$productId.'/variants', [
            'sku' => 'PARENT-1-M',
            'name' => 'Parent Tee M',
            'unit_price' => 2000,
            'option_value_ids' => array_values(array_filter([$valueId])),
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', ProductType::Variant->value)
        ->assertJsonPath('data.parent_id', $productId);

    $this->withToken($token)
        ->getJson('http://variants.localhost/api/products/'.$productId.'/variants')
        ->assertSuccessful()
        ->assertJsonPath('data.0.sku', 'PARENT-1-M');

    $tenant->delete();
});

it('manages manual collections and syncs smart rules', function (): void {
    [$tenant, $token] = catalogueTenantContext('collections.localhost');

    $productId = $this->withToken($token)
        ->postJson('http://collections.localhost/api/products', [
            'sku' => 'COL-1',
            'name' => 'Summer Hat',
            'unit_price' => 1500,
            'type' => ProductType::Simple->value,
        ])
        ->assertCreated()
        ->json('data.id');

    $collectionId = $this->withToken($token)
        ->postJson('http://collections.localhost/api/collections', [
            'name' => 'Summer',
            'slug' => 'summer',
            'type' => CollectionType::Manual->value,
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->putJson('http://collections.localhost/api/collections/'.$collectionId.'/products', [
            'product_ids' => [$productId],
        ])
        ->assertSuccessful();

    $smartId = $this->withToken($token)
        ->postJson('http://collections.localhost/api/collections', [
            'name' => 'Smart Summer',
            'slug' => 'smart-summer',
            'type' => CollectionType::Smart->value,
            'rules' => [
                ['field' => 'title', 'operator' => 'contains', 'value' => 'Summer'],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://collections.localhost/api/collections/'.$smartId.'/sync-rules')
        ->assertSuccessful();

    $tenant->run(function () use ($smartId, $productId): void {
        $collection = Collection::query()->findOrFail($smartId);
        expect($collection->products()->whereKey($productId)->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('blocks advanced catalogue routes when the feature flag is disabled', function (): void {
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCatalogueAdvanced, false);

    $tenant = Tenant::factory()->withDomain('no-catalogue.localhost')->create();
    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://no-catalogue.localhost/api/brands')
        ->assertForbidden();

    $tenant->delete();
});
