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

/**
 * @return array{0: Tenant, 1: array<string, string>}
 */
function tenantContext(): array
{
    $tenant = Tenant::factory()->withDomain('workspace.localhost')->create();

    $token = $tenant->run(function (): string {
        $user = User::query()->where('email', 'admin@tenant.test')->firstOrFail();

        return $user->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, ['Authorization' => 'Bearer '.$token]];
}

it('requires authentication to manage tenant users', function (): void {
    $tenant = Tenant::factory()->withDomain('workspace.localhost')->create();

    $this->getJson('http://workspace.localhost/api/users')
        ->assertUnauthorized();

    $tenant->delete();
});

it('lists tenant users with filtering and pagination', function (): void {
    [$tenant, $headers] = tenantContext();

    $tenant->run(function (): void {
        User::factory()->create([
            'name' => 'Alice Example',
            'email' => 'alice@tenant.test',
        ]);
        User::factory()->create([
            'name' => 'Bob Example',
            'email' => 'bob@tenant.test',
        ]);
    });

    $this->withHeaders($headers)
        ->getJson('http://workspace.localhost/api/users?filter[name]=Alice&sort=name&per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Alice Example')
        ->assertJsonPath('meta.per_page', 10);

    $tenant->delete();
});

it('creates a tenant user', function (): void {
    [$tenant, $headers] = tenantContext();

    $this->withHeaders($headers)
        ->postJson('http://workspace.localhost/api/users', [
            'name' => 'New User',
            'email' => 'new@tenant.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'new@tenant.test')
        ->assertJsonPath('data.roles.0', 'member');

    $tenant->run(function (): void {
        $user = User::query()->where('email', 'new@tenant.test')->firstOrFail();
        expect($user->hasRole('member'))->toBeTrue();
    });

    $tenant->delete();
});

it('creates a tenant user with an explicit admin role', function (): void {
    [$tenant, $headers] = tenantContext();

    $this->withHeaders($headers)
        ->postJson('http://workspace.localhost/api/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@tenant.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])
        ->assertCreated()
        ->assertJsonPath('data.roles.0', 'admin');

    $tenant->run(function (): void {
        expect(User::query()->where('email', 'new-admin@tenant.test')->firstOrFail()->hasRole('admin'))->toBeTrue();
    });

    $tenant->delete();
});

it('updates a tenant user role', function (): void {
    [$tenant, $headers] = tenantContext();

    $userId = $tenant->run(function (): int {
        $user = User::factory()->create([
            'email' => 'promote@tenant.test',
        ]);
        $user->assignRole('member');

        return $user->id;
    });

    $this->withHeaders($headers)
        ->putJson('http://workspace.localhost/api/users/'.$userId, [
            'role' => 'admin',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.roles.0', 'admin');

    $tenant->run(function () use ($userId): void {
        expect(User::query()->findOrFail($userId)->hasRole('admin'))->toBeTrue();
    });

    $tenant->delete();
});

it('shows a tenant user', function (): void {
    [$tenant, $headers] = tenantContext();

    $userId = $tenant->run(fn (): int => User::factory()->create([
        'email' => 'show@tenant.test',
    ])->id);

    $this->withHeaders($headers)
        ->getJson('http://workspace.localhost/api/users/'.$userId)
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'show@tenant.test');

    $tenant->delete();
});

it('updates a tenant user', function (): void {
    [$tenant, $headers] = tenantContext();

    $userId = $tenant->run(fn (): int => User::factory()->create([
        'name' => 'Before',
        'email' => 'before@tenant.test',
    ])->id);

    $this->withHeaders($headers)
        ->putJson('http://workspace.localhost/api/users/'.$userId, [
            'name' => 'After',
            'email' => 'after@tenant.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'After')
        ->assertJsonPath('data.email', 'after@tenant.test');

    $tenant->delete();
});

it('deletes a tenant user', function (): void {
    [$tenant, $headers] = tenantContext();

    $userId = $tenant->run(fn (): int => User::factory()->create([
        'email' => 'delete@tenant.test',
    ])->id);

    $this->withHeaders($headers)
        ->deleteJson('http://workspace.localhost/api/users/'.$userId)
        ->assertSuccessful()
        ->assertJsonPath('message', 'User deleted successfully.');

    $tenant->run(function () use ($userId): void {
        expect(User::query()->whereKey($userId)->exists())->toBeFalse();
    });

    $tenant->delete();
});

it('prevents a user from deleting themselves', function (): void {
    [$tenant, $headers] = tenantContext();

    $adminId = $tenant->run(
        fn (): int => User::query()->where('email', 'admin@tenant.test')->value('id')
    );

    $this->withHeaders($headers)
        ->deleteJson('http://workspace.localhost/api/users/'.$adminId)
        ->assertForbidden();

    $tenant->delete();
});

it('isolates users between tenants', function (): void {
    $tenantA = Tenant::factory()->withDomain('tenant-a.localhost')->create();
    $tenantB = Tenant::factory()->withDomain('tenant-b.localhost')->create();

    $userAId = $tenantA->run(fn (): int => User::factory()->create([
        'email' => 'only-a@tenant.test',
    ])->id);

    $tokenB = $tenantB->run(function (): string {
        $admin = User::query()->where('email', 'admin@tenant.test')->firstOrFail();

        return $admin->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($tokenB)
        ->getJson('http://tenant-b.localhost/api/users/'.$userAId)
        ->assertNotFound();

    $tenantA->delete();
    $tenantB->delete();
});
