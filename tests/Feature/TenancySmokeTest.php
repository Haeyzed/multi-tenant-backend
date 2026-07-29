<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('serves the central home on the central domain', function (): void {
    $this->get('http://localhost/')
        ->assertSuccessful();
});

it('serves the central api health endpoint without initializing tenancy', function (): void {
    $this->getJson('http://localhost/api/health')
        ->assertSuccessful()
        ->assertJsonPath('data.context', 'central')
        ->assertJsonPath('data.tenancy_initialized', false);
});

it('creates a tenant database, migrates, and seeds on tenant creation', function (): void {
    $tenant = Tenant::factory()->withDomain('acme.localhost')->create();

    expect($tenant->domains)->toHaveCount(1)
        ->and($tenant->domains->first()->domain)->toBe('acme.localhost');

    $tenant->run(function (): void {
        expect(User::query()->where('email', 'admin@tenant.test')->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('initializes tenancy and switches database for tenant domain requests', function (): void {
    $tenant = Tenant::factory()->withDomain('acme.localhost')->create();

    $this->get('http://acme.localhost/')
        ->assertSuccessful()
        ->assertSee($tenant->getTenantKey(), false);

    $this->getJson('http://acme.localhost/api/health')
        ->assertSuccessful()
        ->assertJsonPath('data.context', 'tenant')
        ->assertJsonPath('data.tenant_id', $tenant->getTenantKey())
        ->assertJsonPath('data.tenancy_initialized', true);

    $tenant->delete();
});

it('isolates users between tenant databases', function (): void {
    $tenantA = Tenant::factory()->withDomain('tenant-a.localhost')->create();
    $tenantB = Tenant::factory()->withDomain('tenant-b.localhost')->create();

    $tenantA->run(function (): void {
        User::factory()->create([
            'email' => 'alice@tenant-a.test',
        ]);
    });

    $tenantB->run(function (): void {
        User::factory()->create([
            'email' => 'bob@tenant-b.test',
        ]);
    });

    $tenantA->run(function (): void {
        expect(User::query()->where('email', 'alice@tenant-a.test')->exists())->toBeTrue()
            ->and(User::query()->where('email', 'bob@tenant-b.test')->exists())->toBeFalse();
    });

    $tenantB->run(function (): void {
        expect(User::query()->where('email', 'bob@tenant-b.test')->exists())->toBeTrue()
            ->and(User::query()->where('email', 'alice@tenant-a.test')->exists())->toBeFalse();
    });

    expect(DB::connection()->getDatabaseName())->not->toContain($tenantA->getTenantKey());

    $tenantA->delete();
    $tenantB->delete();
});
