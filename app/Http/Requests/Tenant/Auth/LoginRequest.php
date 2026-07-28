<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use App\Http\Requests\Concerns\AuthenticatesWithCredentials;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates tenant-domain login credentials.
 *
 * Accepts email, password, and an optional device name used when creating the
 * Sanctum personal access token for a user in the current tenant database.
 */
class LoginRequest extends FormRequest
{
    use AuthenticatesWithCredentials;
}
