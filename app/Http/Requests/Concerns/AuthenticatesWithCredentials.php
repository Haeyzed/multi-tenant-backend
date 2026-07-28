<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Shared credential validation for central and tenant login endpoints.
 *
 * Used by both central and tenant Auth `LoginRequest` classes. Authorization is
 * open because credentials are verified by the authentication service.
 */
trait AuthenticatesWithCredentials
{
    /**
     * Login is public; credentials are verified after validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Account email address.
             *
             * @example admin@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255'],
            /**
             * Account password.
             *
             * @example secret-password
             */
            'password' => ['required', 'string'],
            /**
             * Label stored on the Sanctum personal access token.
             *
             * @example spa
             *
             * @default api
             */
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Validated credentials ready for the authentication service.
     *
     * @return array{email: string, password: string, device_name: string}
     */
    public function credentials(): array
    {
        return [
            'email' => (string) $this->string('email'),
            'password' => (string) $this->string('password'),
            'device_name' => (string) $this->string('device_name', 'api'),
        ];
    }
}
