<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base HTTP controller for central and tenant API endpoints.
 *
 * Provides authorization helpers used by resource controllers that gate actions
 * through Laravel policies.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
