<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Tenant\Category;
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
function categoryTenantContext(): array
{
    $tenant = Tenant::factory()->withDomain('categories.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('manages categories in the tenant catalog', function (): void {
    [$tenant, $token] = categoryTenantContext();

    $this->withToken($token)
        ->postJson('http://categories.localhost/api/categories', [
            'name' => 'Electronics',
            'description' => 'Electronic goods',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Electronics')
        ->assertJsonPath('data.slug', 'electronics');

    $categoryId = $tenant->run(fn (): int => Category::query()->where('slug', 'electronics')->value('id'));

    $this->withToken($token)
        ->getJson('http://categories.localhost/api/categories?filter[name]=Electronics')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Electronics');

    $this->withToken($token)
        ->putJson('http://categories.localhost/api/categories/'.$categoryId, [
            'description' => 'Updated description',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.description', 'Updated description');

    $this->withToken($token)
        ->deleteJson('http://categories.localhost/api/categories/'.$categoryId)
        ->assertSuccessful();

    $tenant->run(function () use ($categoryId): void {
        expect(Category::query()->whereKey($categoryId)->exists())->toBeFalse()
            ->and(Category::withTrashed()->whereKey($categoryId)->exists())->toBeTrue();
    });

    $tenant->delete();
});
