<?php

declare(strict_types=1);

use App\Models\Central\User;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('lists domains for a tenant', function (): void {
    $tenant = Tenant::factory()->withDomain('primary.localhost')->create(['name' => 'Domain List Co']);
    $tenant->createDomain('secondary.localhost');

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/tenants/'.$tenant->id.'/domains')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Domains retrieved successfully.')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.domain', 'primary.localhost')
        ->assertJsonPath('data.1.domain', 'secondary.localhost');

    $tenant->delete();
});

it('adds a domain to a tenant', function (): void {
    $tenant = Tenant::factory()->withDomain('solo.localhost')->create(['name' => 'Add Domain Co']);

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/domains', [
            'domain' => 'extra.localhost',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.domain', 'extra.localhost')
        ->assertJsonPath('data.tenant_id', $tenant->id);

    expect($tenant->domains()->count())->toBe(2);

    $tenant->delete();
});

it('updates a tenant domain hostname', function (): void {
    $tenant = Tenant::factory()->withDomain('before.localhost')->create(['name' => 'Update Domain Co']);
    /** @var Domain $domain */
    $domain = $tenant->domains()->firstOrFail();

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->putJson('http://localhost/api/tenants/'.$tenant->id.'/domains/'.$domain->id, [
            'domain' => 'after.localhost',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.domain', 'after.localhost');

    expect($domain->fresh()->domain)->toBe('after.localhost');

    $tenant->delete();
});

it('deletes a non-primary domain', function (): void {
    $tenant = Tenant::factory()->withDomain('keep.localhost')->create(['name' => 'Delete Domain Co']);
    $extra = $tenant->createDomain('drop.localhost');

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('http://localhost/api/tenants/'.$tenant->id.'/domains/'.$extra->id)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Domain deleted successfully.');

    expect($tenant->domains()->count())->toBe(1)
        ->and(Domain::query()->whereKey($extra->id)->exists())->toBeFalse();

    $tenant->delete();
});

it('refuses to delete the last remaining domain', function (): void {
    $tenant = Tenant::factory()->withDomain('only.localhost')->create(['name' => 'Last Domain Co']);
    /** @var Domain $domain */
    $domain = $tenant->domains()->firstOrFail();

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('http://localhost/api/tenants/'.$tenant->id.'/domains/'.$domain->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['domain']);

    expect($tenant->domains()->count())->toBe(1);

    $tenant->delete();
});

it('scopes domain routes to the parent tenant', function (): void {
    $alpha = Tenant::factory()->withDomain('alpha-scope.localhost')->create(['name' => 'Alpha Scope']);
    $beta = Tenant::factory()->withDomain('beta-scope.localhost')->create(['name' => 'Beta Scope']);
    /** @var Domain $betaDomain */
    $betaDomain = $beta->domains()->firstOrFail();

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->putJson('http://localhost/api/tenants/'.$alpha->id.'/domains/'.$betaDomain->id, [
            'domain' => 'hijacked.localhost',
        ])
        ->assertNotFound();

    $alpha->delete();
    $beta->delete();
});

it('allows support users to list domains but not mutate them', function (): void {
    $tenant = Tenant::factory()->withDomain('support-domains.localhost')->create(['name' => 'Support Domains']);

    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/tenants/'.$tenant->id.'/domains')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/domains', [
            'domain' => 'forbidden-add.localhost',
        ])
        ->assertForbidden();

    $tenant->delete();
});
