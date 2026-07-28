<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Central\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('exposes activity log entries to platform operators', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $tenant = Tenant::factory()->withDomain('activity.localhost')->create(['name' => 'Activity Co']);
    $tenant->update(['name' => 'Activity Co Renamed']);

    expect(Activity::query()->where('log_name', 'tenants')->count())->toBeGreaterThan(0);

    $this->withToken($token)
        ->getJson('http://localhost/api/activity?filter[log_name]=tenants')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.log_name', 'tenants');

    $activityId = Activity::query()->where('log_name', 'tenants')->latest('id')->value('id');

    $this->withToken($token)
        ->getJson('http://localhost/api/activity/'.$activityId)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $activityId);

    $tenant->delete();
});
