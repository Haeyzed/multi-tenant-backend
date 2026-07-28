<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response(
        'This is your multi-tenant application. The id of the current tenant is '.tenant('id'),
        200,
        ['Content-Type' => 'text/plain'],
    );
})->name('tenant.home');

Route::get('/database', function () {
    return response(
        DB::connection()->getDatabaseName(),
        200,
        ['Content-Type' => 'text/plain'],
    );
})->name('tenant.database');
