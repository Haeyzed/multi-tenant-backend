<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('isolates cache keys between tenants', function (): void {
    $tenantA = Tenant::factory()->withDomain('cache-a.localhost')->create();
    $tenantB = Tenant::factory()->withDomain('cache-b.localhost')->create();

    $tenantA->run(function (): void {
        Cache::put('isolation-probe', 'tenant-a', 60);
        expect(Cache::get('isolation-probe'))->toBe('tenant-a');
    });

    $tenantB->run(function (): void {
        expect(Cache::get('isolation-probe'))->toBeNull();
        Cache::put('isolation-probe', 'tenant-b', 60);
        expect(Cache::get('isolation-probe'))->toBe('tenant-b');
    });

    $tenantA->run(function (): void {
        expect(Cache::get('isolation-probe'))->toBe('tenant-a');
    });

    $tenantA->delete();
    $tenantB->delete();
});

it('scopes the filesystem storage path per tenant', function (): void {
    $tenantA = Tenant::factory()->withDomain('fs-a.localhost')->create();
    $tenantB = Tenant::factory()->withDomain('fs-b.localhost')->create();

    $pathA = $tenantA->run(fn (): string => storage_path());
    $pathB = $tenantB->run(fn (): string => storage_path());

    expect($pathA)->not->toBe($pathB)
        ->and($pathA)->toContain($tenantA->getTenantKey())
        ->and($pathB)->toContain($tenantB->getTenantKey());

    $tenantA->run(function (): void {
        Storage::disk('local')->put('isolation.txt', 'tenant-a');
        expect(Storage::disk('local')->get('isolation.txt'))->toBe('tenant-a');
    });

    $tenantB->run(function (): void {
        expect(Storage::disk('local')->exists('isolation.txt'))->toBeFalse();
    });

    $tenantA->delete();
    $tenantB->delete();
});

it('keeps the central context free of tenant initialization on central health', function (): void {
    $this->getJson('http://localhost/api/health')
        ->assertSuccessful()
        ->assertJsonPath('data.context', 'central')
        ->assertJsonPath('data.tenancy_initialized', false)
        ->assertJsonMissingPath('data.database');
});
