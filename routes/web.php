<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Web Routes
|--------------------------------------------------------------------------
|
| These routes are bound to the configured central domain(s) so they never
| collide with tenant routes that share the same URI paths.
|
*/

foreach (config('tenancy.identification.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return view('welcome');
        })->name('central.home');
    });
}
