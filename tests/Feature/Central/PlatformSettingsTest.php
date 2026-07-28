<?php

declare(strict_types=1);

use App\Models\Central\User;
use App\Models\PlatformSetting;
use App\Services\Central\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows platform admins to upsert and read platform settings', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->putJson('http://localhost/api/settings', [
            'key' => 'support.email',
            'value' => 'help@example.com',
            'type' => 'string',
            'group' => 'support',
            'description' => 'Public support inbox',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.key', 'support.email')
        ->assertJsonPath('data.decoded_value', 'help@example.com');

    $this->withToken($token)
        ->getJson('http://localhost/api/settings/support.email')
        ->assertSuccessful()
        ->assertJsonPath('data.value', 'help@example.com');

    $this->withToken($token)
        ->getJson('http://localhost/api/settings?filter[group]=support')
        ->assertSuccessful()
        ->assertJsonPath('data.0.key', 'support.email');

    expect(app(PlatformSettingService::class)->get('support.email'))
        ->toBe('help@example.com');
});

it('forbids support users from updating settings', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;

    PlatformSetting::factory()->create(['key' => 'app.name', 'value' => 'SaaS']);

    $this->withToken($token)
        ->getJson('http://localhost/api/settings')
        ->assertSuccessful();

    $this->withToken($token)
        ->putJson('http://localhost/api/settings', [
            'key' => 'app.name',
            'value' => 'Nope',
        ])
        ->assertForbidden();
});
