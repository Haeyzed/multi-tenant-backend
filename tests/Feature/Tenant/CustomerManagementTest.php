<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\Tenant\Customer;
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
function customerTenantContext(string $domain = 'crm.localhost'): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages customers with filtering', function (): void {
    [$tenant, $token] = customerTenantContext();

    $this->withToken($token)
        ->postJson('http://crm.localhost/api/customers', [
            'name' => 'Acme Buyer',
            'email' => 'buyer@acme.test',
            'company' => 'Acme',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Acme Buyer')
        ->assertJsonPath('data.email', 'buyer@acme.test');

    $tenant->run(function (): void {
        Customer::factory()->create(['name' => 'Other Co', 'company' => 'Other']);
    });

    $this->withToken($token)
        ->getJson('http://crm.localhost/api/customers?filter[company]=Acme&sort=name')
        ->assertSuccessful()
        ->assertJsonPath('data.0.company', 'Acme')
        ->assertJsonCount(1, 'data');

    $customerId = $tenant->run(fn (): int => Customer::query()->where('email', 'buyer@acme.test')->value('id'));

    $this->withToken($token)
        ->putJson('http://crm.localhost/api/customers/'.$customerId, [
            'notes' => 'VIP',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.notes', 'VIP');

    $this->withToken($token)
        ->deleteJson('http://crm.localhost/api/customers/'.$customerId)
        ->assertSuccessful();

    $tenant->run(function () use ($customerId): void {
        expect(Customer::query()->whereKey($customerId)->exists())->toBeFalse()
            ->and(Customer::withTrashed()->whereKey($customerId)->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('forbids members from creating customers', function (): void {
    $tenant = Tenant::factory()->withDomain('crm-member.localhost')->create();

    $token = $tenant->run(function (): string {
        $member = User::factory()->create(['email' => 'member@crm.test']);
        $member->assignRole('member');

        return $member->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->postJson('http://crm-member.localhost/api/customers', [
            'name' => 'Blocked',
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->getJson('http://crm-member.localhost/api/customers')
        ->assertSuccessful();

    $tenant->delete();
});
