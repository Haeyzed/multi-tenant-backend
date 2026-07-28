<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Loaded by App\Providers\TenancyServiceProvider with the "tenant" middleware
| group. Identification middleware ensures these never run on central domains.
|
*/

Route::middleware([
    Middleware\InitializeTenancyByDomain::class,
    Middleware\PreventAccessFromUnwantedDomains::class,
])->group(function () {
    Route::middleware([
        'web',
        Middleware\ScopeSessions::class,
    ])->group(base_path('routes/tenant/web.php'));

    Route::middleware('api')
        ->prefix('api')
        ->group(base_path('routes/tenant/api.php'));
});
