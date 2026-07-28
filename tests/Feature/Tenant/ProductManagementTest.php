<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
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
function productTenantContext(): array
{
    $tenant = Tenant::factory()->withDomain('catalog.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages products in the tenant catalog', function (): void {
    [$tenant, $token] = productTenantContext();
    $currency = strtoupper((string) config('billing.default_currency'));

    $this->withToken($token)
        ->postJson('http://catalog.localhost/api/products', [
            'sku' => 'widget-1',
            'name' => 'Widget',
            'unit_price' => 2500,
            'currency' => $currency,
        ])
        ->assertCreated()
        ->assertJsonPath('data.sku', 'WIDGET-1')
        ->assertJsonPath('data.unit_price', 2500)
        ->assertJsonPath('data.currency', $currency);

    $productId = $tenant->run(fn (): int => Product::query()->where('sku', 'WIDGET-1')->value('id'));

    $this->withToken($token)
        ->getJson('http://catalog.localhost/api/products?filter[sku]=WIDGET')
        ->assertSuccessful()
        ->assertJsonPath('data.0.sku', 'WIDGET-1');

    $this->withToken($token)
        ->putJson('http://catalog.localhost/api/products/'.$productId, [
            'unit_price' => 3000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.unit_price', 3000);

    $this->withToken($token)
        ->deleteJson('http://catalog.localhost/api/products/'.$productId)
        ->assertSuccessful();

    $tenant->delete();
});
