<?php

declare(strict_types=1);

use App\Enums\Tenant\OrderStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\BusinessSetting;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Order;
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
function erpOpsTenantContext(string $domain = 'erp-ops.localhost'): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages employees', function (): void {
    [$tenant, $token] = erpOpsTenantContext('employees.localhost');

    $this->withToken($token)
        ->postJson('http://employees.localhost/api/employees', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'job_title' => 'Engineer',
            'hired_at' => '2026-01-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.job_title', 'Engineer');

    $employeeId = $tenant->run(fn (): int => Employee::query()->where('email', 'ada@example.com')->value('id'));

    $this->withToken($token)
        ->getJson('http://employees.localhost/api/employees?filter[name]=Ada')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Ada Lovelace');

    $this->withToken($token)
        ->putJson('http://employees.localhost/api/employees/'.$employeeId, [
            'job_title' => 'Lead Engineer',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.job_title', 'Lead Engineer');

    $this->withToken($token)
        ->deleteJson('http://employees.localhost/api/employees/'.$employeeId)
        ->assertSuccessful();

    $tenant->run(function () use ($employeeId): void {
        expect(Employee::query()->whereKey($employeeId)->exists())->toBeFalse()
            ->and(Employee::withTrashed()->whereKey($employeeId)->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('upserts and reads business settings', function (): void {
    [$tenant, $token] = erpOpsTenantContext('settings.localhost');

    $this->withToken($token)
        ->putJson('http://settings.localhost/api/settings', [
            'key' => 'company.name',
            'value' => 'Acme Corp',
            'type' => 'string',
            'group' => 'company',
            'description' => 'Legal business name',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.key', 'company.name')
        ->assertJsonPath('data.decoded_value', 'Acme Corp');

    $this->withToken($token)
        ->getJson('http://settings.localhost/api/settings/company.name')
        ->assertSuccessful()
        ->assertJsonPath('data.value', 'Acme Corp');

    $map = $this->withToken($token)
        ->getJson('http://settings.localhost/api/settings/map')
        ->assertSuccessful()
        ->json('data');

    expect($map['company.name'])->toBe('Acme Corp');

    $this->withToken($token)
        ->getJson('http://settings.localhost/api/settings?filter[group]=company')
        ->assertSuccessful()
        ->assertJsonPath('data.0.key', 'company.name');

    $tenant->run(function (): void {
        expect(BusinessSetting::query()->where('key', 'company.name')->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('returns sales summary and low stock reports', function (): void {
    [$tenant, $token] = erpOpsTenantContext('reports.localhost');

    $tenant->run(function (): void {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'LOW-1',
            'name' => 'Low Stock Item',
            'unit_price' => 1000,
            'stock_quantity' => 2,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed,
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);
    });

    $this->withToken($token)
        ->getJson('http://reports.localhost/api/reports/sales-summary')
        ->assertSuccessful()
        ->assertJsonPath('data.orders_count', 1)
        ->assertJsonPath('data.revenue_total', 1000);

    $this->withToken($token)
        ->getJson('http://reports.localhost/api/reports/top-products?limit=5')
        ->assertSuccessful()
        ->assertJsonPath('data.0.product_sku', 'LOW-1')
        ->assertJsonPath('data.0.quantity_sold', 1);

    $this->withToken($token)
        ->getJson('http://reports.localhost/api/reports/low-stock?threshold=5')
        ->assertSuccessful()
        ->assertJsonPath('data.0.sku', 'LOW-1')
        ->assertJsonPath('data.0.stock_quantity', 2);

    $tenant->delete();
});
