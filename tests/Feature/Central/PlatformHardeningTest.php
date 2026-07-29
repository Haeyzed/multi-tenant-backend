<?php

declare(strict_types=1);

use App\Models\Central\Activity;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('soft deletes central users instead of hard deleting them', function (): void {
    $user = User::factory()->platformAdmin()->create([
        'email' => 'soft-delete@example.com',
    ]);

    $user->delete();

    expect(User::query()->where('email', 'soft-delete@example.com')->exists())->toBeFalse()
        ->and(User::withTrashed()->where('email', 'soft-delete@example.com')->exists())->toBeTrue()
        ->and(User::withTrashed()->where('email', 'soft-delete@example.com')->first()?->trashed())->toBeTrue();
});

it('records activity when a tenant name is updated', function (): void {
    $tenant = Tenant::factory()->withDomain('activity.localhost')->create([
        'name' => 'Before Activity',
    ]);

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->putJson('http://localhost/api/tenants/'.$tenant->id, [
            'name' => 'After Activity',
        ])
        ->assertSuccessful();

    expect(
        Activity::query()
            ->where('log_name', 'tenants')
            ->where('subject_type', $tenant->getMorphClass())
            ->where('subject_id', $tenant->getKey())
            ->where('event', 'updated')
            ->exists()
    )->toBeTrue();

    $tenant->delete();
});

it('records activity when a domain is created', function (): void {
    $tenant = Tenant::factory()->withDomain('domain-activity.localhost')->create([
        'name' => 'Domain Activity Co',
    ]);

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/domains', [
            'domain' => 'logged.localhost',
        ])
        ->assertCreated();

    /** @var Domain $domain */
    $domain = Domain::query()->where('domain', 'logged.localhost')->firstOrFail();

    expect(
        Activity::query()
            ->where('log_name', 'domains')
            ->where('event', 'created')
            ->where('subject_type', $domain->getMorphClass())
            ->where('subject_id', $domain->getKey())
            ->exists()
    )->toBeTrue();

    $tenant->delete();
});

it('keeps tenancy lifecycle synchronous in the testing environment', function (): void {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(config('queue.default'))->toBe('sync')
        ->and((bool) env('TENANCY_QUEUE_LIFECYCLE', ! app()->environment(['local', 'testing'])))->toBeFalse();

    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Sync Lifecycle Co',
            'domain' => 'sync-lifecycle.localhost',
        ])
        ->assertCreated();

    $tenant = Tenant::query()->findOrFail($response->json('data.id'));

    $tenant->run(function (): void {
        expect(TenantUser::query()->where('email', 'admin@tenant.test')->exists())->toBeTrue();
    });

    $tenant->delete();
});
