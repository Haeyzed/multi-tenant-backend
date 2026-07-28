<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\ChangePasswordRequest;
use App\Http\Requests\Central\Auth\ForgotPasswordRequest;
use App\Http\Requests\Central\Auth\LoginRequest;
use App\Http\Requests\Central\Auth\ResetPasswordRequest;
use App\Http\Requests\Central\Auth\UpdateProfileRequest;
use App\Http\Resources\Central\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Central\User;
use App\Services\Central\AuthenticationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central-domain authentication and account lifecycle endpoints.
 *
 * Issues Sanctum tokens and manages profile, password, and email verification
 * for platform (central) users.
 */
#[Group('Authentication')]
class AuthController extends Controller
{
    public function __construct(private AuthenticationService $authentication) {}

    /**
     * Authenticate a central user.
     *
     * Validates credentials against the central users table and returns a Bearer
     * Sanctum token with the authenticated user payload.
     *
     * @unauthenticated
     *
     * @operationId centralLogin
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: array{
     *         token: string,
     *         token_type: 'Bearer',
     *         user: UserResource
     *     },
     *     meta: null,
     *     errors: null
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();

        $result = $this->authentication->login(
            email: $credentials['email'],
            password: $credentials['password'],
            deviceName: $credentials['device_name'],
        );

        return ApiResponse::success(
            data: [
                'token' => $result['token']->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($result['user'])->resolve(),
            ],
            message: 'Authenticated successfully.',
        );
    }

    /**
     * Get the authenticated central user.
     *
     * @operationId centralMe
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->loadMissing('roles'))
            ->withMessage('Authenticated user retrieved successfully.');
    }

    /**
     * Update the authenticated central user's profile.
     *
     * Changing the email clears email verification until re-verified.
     *
     * @operationId centralUpdateProfile
     */
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user = $this->authentication->updateProfile($user, $request->profileData());

        return new UserResource($user)
            ->withMessage('Profile updated successfully.');
    }

    /**
     * Change the authenticated central user's password.
     *
     * Revokes all Sanctum tokens after a successful change.
     *
     * @operationId centralChangePassword
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authentication->changePassword(
            $user,
            (string) $request->string('current_password'),
            (string) $request->string('password'),
        );

        return ApiResponse::success(message: 'Password changed successfully.');
    }

    /**
     * Request a password reset link for a central user.
     *
     * @unauthenticated
     *
     * @operationId centralForgotPassword
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->authentication->sendPasswordResetLink(
            (string) $request->string('email'),
        );

        return ApiResponse::success(message: $message);
    }

    /**
     * Reset a central user's password using a broker token.
     *
     * @unauthenticated
     *
     * @operationId centralResetPassword
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authentication->resetPassword($request->resetCredentials());

        return ApiResponse::success(message: __('passwords.reset'));
    }

    /**
     * Resend the email verification notification.
     *
     * @operationId centralSendVerificationEmail
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authentication->sendEmailVerificationNotification($user);

        return ApiResponse::success(message: 'Verification link sent.');
    }

    /**
     * Verify a central user's email via a signed link.
     *
     * @unauthenticated
     *
     * @operationId centralVerifyEmail
     */
    public function verifyEmail(Request $request, string $id, string $hash): UserResource
    {
        /** @var User $user */
        $user = User::query()->findOrFail($id);

        $user = $this->authentication->verifyEmail($user, $hash);

        return new UserResource($user)
            ->withMessage('Email verified successfully.');
    }

    /**
     * Log out the authenticated central user.
     *
     * @operationId centralLogout
     *
     * @response array{
     *     success: true,
     *     message: string,
     *     data: null,
     *     meta: null,
     *     errors: null
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authentication->logout($request);

        return ApiResponse::success(message: 'Logged out successfully.');
    }
}
