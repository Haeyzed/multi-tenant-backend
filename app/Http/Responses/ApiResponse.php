<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds JSON responses that match the shared API envelope.
 *
 * Prefer returning Eloquent API Resources when possible so Scramble can infer
 * schemas. Use this helper for non-resource payloads (login tokens, empty
 * deletes) or when a custom HTTP status must be set explicitly.
 */
final class ApiResponse
{
    /**
     * Build a successful envelope response.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operation completed successfully.',
        ?array $meta = null,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::resolveData($data),
            'meta' => $meta,
            'errors' => null,
        ], $status);
    }

    /**
     * Build a failed envelope response.
     *
     * @param  array<string, mixed>|null  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public static function error(
        string $message,
        ?array $errors = null,
        int $status = Response::HTTP_BAD_REQUEST,
        mixed $data = null,
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => self::resolveData($data),
            'meta' => $meta,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Resolve JsonResource / ResourceCollection instances to plain arrays.
     */
    private static function resolveData(mixed $data): mixed
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->resolve();
        }

        return $data;
    }
}
