<?php

declare(strict_types=1);

use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\CouponType;
use App\Models\Central\Coupon;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows platform admins to manage coupons', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $created = $this->withToken($token)
        ->postJson('http://localhost/api/coupons', [
            'code' => 'save20',
            'type' => CouponType::Percent->value,
            'amount' => 20,
            'duration' => CouponDuration::Once->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'SAVE20')
        ->assertJsonPath('data.duration', CouponDuration::Once->value);

    $couponId = $created->json('data.id');

    $this->withToken($token)
        ->getJson('http://localhost/api/coupons')
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'SAVE20');

    $this->withToken($token)
        ->putJson('http://localhost/api/coupons/'.$couponId, [
            'is_active' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);

    $this->withToken($token)
        ->deleteJson('http://localhost/api/coupons/'.$couponId)
        ->assertSuccessful();

    expect(Coupon::query()->whereKey($couponId)->exists())->toBeFalse();
});

it('forbids support users from creating coupons', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/coupons')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://localhost/api/coupons', [
            'code' => 'nope',
            'type' => CouponType::Fixed->value,
            'amount' => 500,
            'duration' => CouponDuration::Once->value,
        ])
        ->assertForbidden();
});
