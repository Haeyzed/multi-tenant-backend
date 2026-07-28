<?php

declare(strict_types=1);

use App\Models\Central\User;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

function centralAuthHeaders(): array
{
    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    return ['Authorization' => 'Bearer '.$token];
}

it('requires authentication to manage tenants', function (): void {
    $this->getJson('http://localhost/api/tenants')
        ->assertUnauthorized();
});

it('provisions a tenant with a domain database and seed data', function (): void {
    $response = $this->withHeaders(centralAuthHeaders())
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Acme Corporation',
            'domain' => 'acme.localhost',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Tenant provisioned successfully.')
        ->assertJsonPath('data.name', 'Acme Corporation')
        ->assertJsonPath('data.domains.0.domain', 'acme.localhost');

    $tenantId = $response->json('data.id');

    expect(Tenant::query()->whereKey($tenantId)->exists())->toBeTrue();

    $tenant = Tenant::query()->findOrFail($tenantId);

    $tenant->run(function (): void {
        expect(TenantUser::query()->where('email', 'admin@tenant.test')->exists())->toBeTrue();
    });

    $this->getJson('http://acme.localhost/api/health')
        ->assertSuccessful()
        ->assertJsonPath('data.tenant_id', $tenantId);

    $tenant->delete();
});

it('lists tenants with filtering sorting and pagination', function (): void {
    $alpha = Tenant::factory()->withDomain('alpha.localhost')->create(['name' => 'Alpha Co']);
    $beta = Tenant::factory()->withDomain('beta.localhost')->create(['name' => 'Beta Co']);

    $this->withHeaders(centralAuthHeaders())
        ->getJson('http://localhost/api/tenants?filter[name]=Alpha&sort=name&per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Alpha Co')
        ->assertJsonPath('meta.per_page', 10);

    $alpha->delete();
    $beta->delete();
});

it('shows a tenant', function (): void {
    $tenant = Tenant::factory()->withDomain('show-me.localhost')->create([
        'name' => 'Show Me',
    ]);

    $this->withHeaders(centralAuthHeaders())
        ->getJson('http://localhost/api/tenants/'.$tenant->id)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.name', 'Show Me')
        ->assertJsonPath('data.domains.0.domain', 'show-me.localhost');

    $tenant->delete();
});

it('updates a tenant name and domain', function (): void {
    $tenant = Tenant::factory()->withDomain('old.localhost')->create([
        'name' => 'Old Name',
    ]);

    $this->withHeaders(centralAuthHeaders())
        ->putJson('http://localhost/api/tenants/'.$tenant->id, [
            'name' => 'New Name',
            'domain' => 'new.localhost',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.domains.0.domain', 'new.localhost');

    $tenant->delete();
});

it('deletes a tenant and its database', function (): void {
    $tenant = Tenant::factory()->withDomain('delete-me.localhost')->create([
        'name' => 'Delete Me',
    ]);

    $tenantId = $tenant->id;
    $databaseName = $tenant->database()->getName();

    $this->withHeaders(centralAuthHeaders())
        ->deleteJson('http://localhost/api/tenants/'.$tenantId)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Tenant deleted successfully.');

    expect(Tenant::query()->whereKey($tenantId)->exists())->toBeFalse();

    if (DB::getDriverName() === 'sqlite') {
        expect(file_exists(database_path($databaseName)))->toBeFalse();
    }
});

it('validates unique domains when provisioning', function (): void {
    $tenant = Tenant::factory()->withDomain('taken.localhost')->create();

    $this->withHeaders(centralAuthHeaders())
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Another',
            'domain' => 'taken.localhost',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['domain']]);

    $tenant->delete();
});
