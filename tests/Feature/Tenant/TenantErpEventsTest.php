<?php

declare(strict_types=1);

use App\Enums\Tenant\OrderStatus;
use App\Events\Tenant\Erp\CustomerCreated;
use App\Events\Tenant\Erp\OrderConfirmed;
use App\Events\Tenant\Erp\ProductCreated;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('dispatches ERP domain events for customers, products, and confirmed orders', function (): void {
    $tenant = Tenant::factory()->withDomain('erp-events.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    Event::fake([CustomerCreated::class, ProductCreated::class, OrderConfirmed::class]);

    $this->withToken($token)
        ->postJson('http://erp-events.localhost/api/customers', [
            'name' => 'Event Customer',
            'email' => 'events@example.test',
        ])
        ->assertCreated();

    Event::assertDispatched(CustomerCreated::class, function (CustomerCreated $event) use ($tenant): bool {
        return $event->tenantId === (string) $tenant->getTenantKey()
            && $event->customer->email === 'events@example.test';
    });

    $this->withToken($token)
        ->postJson('http://erp-events.localhost/api/products', [
            'sku' => 'EVT-1',
            'name' => 'Event Product',
            'unit_price' => 500,
        ])
        ->assertCreated();

    Event::assertDispatched(ProductCreated::class);

    [$customerId, $productId] = $tenant->run(function (): array {
        return [
            Customer::query()->where('email', 'events@example.test')->value('id'),
            Product::query()->where('sku', 'EVT-1')->value('id'),
        ];
    });

    $this->withToken($token)
        ->postJson('http://erp-events.localhost/api/orders', [
            'customer_id' => $customerId,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ])
        ->assertCreated();

    Event::assertDispatched(OrderConfirmed::class);

    $tenant->delete();
});
