<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps exceptions to the shared API error envelope for JSON/API requests.
 *
 * Wired from `bootstrap/app.php` for requests that match `api/*` or expect JSON.
 * Successful responses are unchanged; this class only shapes error payloads as
 * `{ success: false, message, data, meta, errors }`.
 */
final class ApiExceptionRenderer
{
    /**
     * Whether this renderer should handle the given request.
     */
    public function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Convert a throwable into an envelope JSON response, or null to defer.
     *
     * Returns null when the request is not an API/JSON consumer so Laravel's
     * default exception handling (HTML, etc.) can proceed.
     */
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRender($request)) {
            return null;
        }

        return match (true) {
            $exception instanceof ValidationException => $this->validation($exception),
            $exception instanceof AuthenticationException => $this->unauthenticated($exception),
            $exception instanceof AuthorizationException => $this->forbidden($exception),
            $exception instanceof EntitlementLimitExceededException => $this->entitlementLimit($exception),
            $exception instanceof ModelNotFoundException => $this->notFound('Resource not found.'),
            $exception instanceof TenantCouldNotBeIdentifiedException => $this->notFound('Tenant could not be identified.'),
            $exception instanceof HttpExceptionInterface => $this->http($exception),
            default => $this->serverError($exception),
        };
    }

    /**
     * Plan entitlement / quota denial (HTTP 403).
     */
    private function entitlementLimit(EntitlementLimitExceededException $exception): JsonResponse
    {
        return ApiResponse::error(
            message: $exception->getMessage(),
            errors: [
                'feature' => [$exception->featureKey()],
            ],
            status: Response::HTTP_FORBIDDEN,
            meta: [
                'feature' => $exception->featureKey(),
                'limit' => $exception->limit(),
                'current' => $exception->current(),
            ],
        );
    }

    /**
     * Validation failure (typically HTTP 422) with field error bags.
     */
    private function validation(ValidationException $exception): JsonResponse
    {
        return ApiResponse::error(
            message: $exception->getMessage(),
            errors: $exception->errors(),
            status: $exception->status,
        );
    }

    /**
     * Missing or invalid authentication (HTTP 401).
     */
    private function unauthenticated(AuthenticationException $exception): JsonResponse
    {
        return ApiResponse::error(
            message: $exception->getMessage() ?: 'Unauthenticated.',
            status: Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * Failed authorization / policy denial (HTTP 403).
     */
    private function forbidden(AuthorizationException $exception): JsonResponse
    {
        return ApiResponse::error(
            message: $exception->getMessage() ?: 'This action is unauthorized.',
            status: Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Missing resource or unidentified tenant (HTTP 404).
     */
    private function notFound(string $message): JsonResponse
    {
        return ApiResponse::error(
            message: $message,
            status: Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * Symfony/Laravel HTTP exceptions using their declared status code.
     */
    private function http(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $message = $exception->getMessage() !== ''
            ? $exception->getMessage()
            : (Response::$statusTexts[$status] ?? 'HTTP Error');

        return ApiResponse::error(
            message: $message,
            status: $status,
        );
    }

    /**
     * Unexpected failures (HTTP 500).
     *
     * In debug mode the exception message and file/line meta are included;
     * otherwise a generic "Server Error" message is returned.
     */
    private function serverError(Throwable $exception): JsonResponse
    {
        $message = app()->hasDebugModeEnabled()
            ? $exception->getMessage()
            : 'Server Error';

        return ApiResponse::error(
            message: $message !== '' ? $message : 'Server Error',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
            meta: app()->hasDebugModeEnabled() ? [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : null,
        );
    }
}
