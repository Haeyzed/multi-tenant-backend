<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('reads and updates typed store configuration', function (): void {
    $tenant = Tenant::factory()->withDomain('store-config.localhost')->create();
    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://store-config.localhost/api/store-config')
        ->assertSuccessful()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.currency', null);

    $this->withToken($token)
        ->putJson('http://store-config.localhost/api/store-config', [
            'name' => 'Acme Store',
            'email' => 'hello@acme.test',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'locale' => 'en_NG',
            'tax_inclusive' => true,
            'address' => '12 Market Street',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Acme Store')
        ->assertJsonPath('data.email', 'hello@acme.test')
        ->assertJsonPath('data.timezone', 'Africa/Lagos')
        ->assertJsonPath('data.currency', 'NGN')
        ->assertJsonPath('data.tax_inclusive', true);

    $this->withToken($token)
        ->getJson('http://store-config.localhost/api/store-config')
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Acme Store')
        ->assertJsonPath('data.currency', 'NGN');

    $tenant->delete();
});
