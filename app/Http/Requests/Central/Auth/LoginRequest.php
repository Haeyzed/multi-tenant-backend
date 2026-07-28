<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use App\Http\Requests\Concerns\AuthenticatesWithCredentials;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates central-domain login credentials.
 *
 * Accepts email, password, and an optional device name used when creating the
 * Sanctum personal access token for a platform (central) user.
 */
class LoginRequest extends FormRequest
{
    use AuthenticatesWithCredentials;
}
