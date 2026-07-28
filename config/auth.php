<?php

declare(strict_types=1);

use App\Models\Central\User;
use App\Models\Tenant\User as TenantUser;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'central_users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | API token auth uses Sanctum (auth:sanctum). Session guards exist so Spatie
    | Permission can resolve distinct central vs tenant guard names.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'central_users',
        ],
        'tenant' => [
            'driver' => 'session',
            'provider' => 'tenant_users',
        ],
    ],

    'providers' => [
        'central_users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_CENTRAL_MODEL', User::class),
        ],
        'tenant_users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_TENANT_MODEL', TenantUser::class),
        ],
    ],

    'passwords' => [
        'central_users' => [
            'provider' => 'central_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'tenant_users' => [
            'provider' => 'tenant_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
