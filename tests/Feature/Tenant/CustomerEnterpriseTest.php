<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\CustomerAddressType;
use App\Enums\Tenant\CustomerNoteType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use App\Models\Tenant\CustomerContact;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\CustomerNote;
use App\Models\Tenant\CustomerTag;
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
function customerEnterpriseContext(string $domain = 'crm-enterprise.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCustomersAdvanced, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages customer groups and enterprise customer fields', function (): void {
    [$tenant, $token] = customerEnterpriseContext();

    $group = $this->withToken($token)
        ->postJson('http://crm-enterprise.localhost/api/customer-groups', [
            'name' => 'Wholesale',
            'code' => 'WHL',
            'discount_percent' => 10,
            'price_list_id' => 42,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'WHL')
        ->assertJsonPath('data.price_list_id', 42);

    $groupId = $group->json('data.id');

    $customer = $this->withToken($token)
        ->postJson('http://crm-enterprise.localhost/api/customers', [
            'name' => 'Bulk Buyer',
            'email' => 'bulk@buyer.test',
            'customer_group_id' => $groupId,
            'credit_limit' => 500000,
            'currency' => 'usd',
            'tax_exempt' => true,
            'tax_id' => 'VAT-123',
        ])
        ->assertCreated()
        ->assertJsonPath('data.customer_group_id', $groupId)
        ->assertJsonPath('data.credit_limit', 500000)
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.tax_exempt', true);

    expect($customer->json('data.code'))->not->toBeNull();

    $tenant->run(function () use ($groupId): void {
        expect(CustomerGroup::query()->whereKey($groupId)->exists())->toBeTrue()
            ->and(Customer::query()->where('customer_group_id', $groupId)->count())->toBe(1);
    });

    $tenant->delete();
});

it('manages nested addresses, contacts, notes, and tags', function (): void {
    [$tenant, $token] = customerEnterpriseContext('crm-nested.localhost');

    $customerId = $this->withToken($token)
        ->postJson('http://crm-nested.localhost/api/customers', [
            'name' => 'Nested Co',
            'email' => 'nested@co.test',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://crm-nested.localhost/api/customers/'.$customerId.'/addresses', [
            'type' => CustomerAddressType::Billing->value,
            'line1' => '1 Market Street',
            'city' => 'Lagos',
            'country' => 'ng',
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'billing')
        ->assertJsonPath('data.country', 'NG')
        ->assertJsonPath('data.is_default', true);

    $this->withToken($token)
        ->postJson('http://crm-nested.localhost/api/customers/'.$customerId.'/contacts', [
            'name' => 'Jane Buyer',
            'email' => 'jane@co.test',
            'is_primary' => true,
            'title' => 'Procurement',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $this->withToken($token)
        ->postJson('http://crm-nested.localhost/api/customers/'.$customerId.'/notes', [
            'type' => CustomerNoteType::Credit->value,
            'subject' => 'Terms',
            'body' => 'Net 30 approved',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'credit');

    $tagId = $this->withToken($token)
        ->postJson('http://crm-nested.localhost/api/customer-tags', [
            'name' => 'VIP',
            'color' => '#112233',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'vip')
        ->json('data.id');

    $this->withToken($token)
        ->putJson('http://crm-nested.localhost/api/customers/'.$customerId.'/tags', [
            'tag_ids' => [$tagId],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.tags.0.id', $tagId);

    $tenant->run(function () use ($customerId, $tagId): void {
        expect(CustomerAddress::query()->where('customer_id', $customerId)->count())->toBe(1)
            ->and(CustomerContact::query()->where('customer_id', $customerId)->count())->toBe(1)
            ->and(CustomerNote::query()->where('customer_id', $customerId)->count())->toBe(1)
            ->and(Customer::query()->find($customerId)?->tags()->whereKey($tagId)->exists())->toBeTrue()
            ->and(CustomerTag::query()->whereKey($tagId)->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('blocks advanced customer CRM when the feature flag is disabled', function (): void {
    [$tenant, $token] = customerEnterpriseContext('crm-flag.localhost');
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCustomersAdvanced, false);

    $this->withToken($token)
        ->postJson('http://crm-flag.localhost/api/customer-groups', [
            'name' => 'Blocked',
            'code' => 'BLK',
        ])
        ->assertForbidden();

    $tenant->delete();
});
